<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DeliverySlot;
use App\Models\Option;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BnplTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config([
            'shop.tabby' => ['base_url' => 'https://tabby.test', 'public_key' => 'pk', 'secret_key' => 'sk', 'merchant_code' => 'ZB'],
            'shop.tamara' => ['base_url' => 'https://tamara.test', 'api_token' => 'tt', 'notification_token' => 'nt', 'instalments' => 4],
        ]);
    }

    private function placeWith(string $method): \Illuminate\Testing\TestResponse
    {
        $user = User::create(['name' => 'سارة علي', 'phone' => '+971501234567']);
        $address = $user->addresses()->create(['area_id' => Area::where('name_en', 'Mirdif')->value('id'), 'details' => 'Villa 1']);
        $product = Product::where('name_en', 'Naimi sheep')->first();

        return $this->actingAs($user)->postJson('/api/v1/orders', [
            'branch_id' => $product->branch_id,
            'address_id' => $address->id,
            'delivery_slot_id' => DeliverySlot::orderBy('date')->first()->id,
            'payment_method' => $method,
            'items' => [[
                'product_id' => $product->id,
                'variant_id' => $product->variants()->first()->id,
                'quantity' => 1,
                'option_ids' => Option::whereIn('name_en', ['Arabic cut, 8 pieces', 'Carton (no ice)'])->pluck('id')->all(),
            ]],
        ]);
    }

    public function test_tabby_checkout_then_return_verifies_and_captures(): void
    {
        Http::fake([
            'tabby.test/api/v2/checkout' => Http::response(['id' => 'sess', 'status' => 'created', 'payment' => ['id' => 'pay_1'],
                'configuration' => ['available_products' => ['installments' => [['web_url' => 'https://checkout.tabby.ai/x']]]]]),
            'tabby.test/api/v2/payments/pay_1' => Http::response(['id' => 'pay_1', 'status' => 'AUTHORIZED', 'amount' => '1200.15']),
            'tabby.test/api/v2/payments/pay_1/captures' => Http::response(['status' => 'CLOSED']),
        ]);

        $res = $this->placeWith('tabby')->assertCreated()->assertJsonPath('checkout_url', 'https://checkout.tabby.ai/x');
        $order = Order::find($res->json('order.id'));
        $this->assertSame('pay_1', $order->payment_reference);
        $this->assertSame('1200.15', $order->total); // 1143 + 5% VAT

        // A "failure" redirect never marks paid.
        $this->get("/api/v1/payments/tabby/return/{$order->id}?status=failure")->assertOk()->assertSee('لم يكتمل الدفع');
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->get("/api/v1/payments/tabby/return/{$order->id}?status=success")->assertOk()->assertSee('تم الدفع بنجاح');
        $this->assertSame('paid', $order->fresh()->payment_status);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/captures') && $r['amount'] === '1200.15' && $r->hasHeader('Authorization', 'Bearer sk'));
    }

    public function test_tabby_rejection_keeps_order_unpaid_and_customer_can_switch_to_cash(): void
    {
        Http::fake(['tabby.test/*' => Http::response(['status' => 'rejected', 'configuration' => ['available_products' => []]])]);

        $res = $this->placeWith('tabby')->assertCreated()->assertJsonPath('payment_error', fn ($m) => str_contains($m, 'تابي'));
        $id = $res->json('order.id');

        $this->postJson("/api/v1/orders/$id/payment-method", ['payment_method' => 'cod'])
            ->assertOk()->assertJsonPath('order.payment_method', 'cod');
    }

    public function test_tamara_webhook_authorises_and_captures_only_with_valid_token(): void
    {
        Http::fake([
            'tamara.test/checkout' => Http::response(['order_id' => 'tm_1', 'checkout_url' => 'https://checkout.tamara.co/y']),
            'tamara.test/orders/tm_1' => Http::response(['status' => 'approved', 'total_amount' => ['amount' => 1200.15, 'currency' => 'AED']]),
            'tamara.test/orders/tm_1/authorise' => Http::response(['status' => 'authorised']),
            'tamara.test/payments/capture' => Http::response(['status' => 'fully_captured']),
        ]);

        $res = $this->placeWith('tamara')->assertCreated()->assertJsonPath('checkout_url', 'https://checkout.tamara.co/y');
        $order = Order::find($res->json('order.id'));

        $this->postJson('/api/v1/webhooks/tamara?tamaraToken=wrong', ['order_id' => 'tm_1'])->assertStatus(401);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->postJson('/api/v1/webhooks/tamara?tamaraToken=nt', ['order_id' => 'tm_1', 'event_type' => 'order_approved'])->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_amount_mismatch_is_never_marked_paid(): void
    {
        Http::fake([
            'tabby.test/api/v2/checkout' => Http::response(['payment' => ['id' => 'pay_2'],
                'configuration' => ['available_products' => ['installments' => [['web_url' => 'https://x']]]]]),
            'tabby.test/api/v2/payments/pay_2' => Http::response(['status' => 'AUTHORIZED', 'amount' => '1.00']),
        ]);
        $order = Order::find($this->placeWith('tabby')->json('order.id'));
        $this->get("/api/v1/payments/tabby/return/{$order->id}?status=success")->assertSee('لم يكتمل الدفع');
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_bnpl_is_refused_when_not_configured(): void
    {
        config(['shop.tamara.api_token' => null]);
        $this->placeWith('tamara')->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }
}
