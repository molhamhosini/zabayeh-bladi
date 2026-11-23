<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliverySlot;
use App\Models\Emirate;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function dubaiArea(): Area
    {
        return Area::where('name_en', 'Mirdif')->first();
    }

    private function customer(): User
    {
        return User::create(['name' => 'Test', 'phone' => '+971501234567']);
    }

    private function orderPayload(User $user, array $overrides = []): array
    {
        $address = $user->addresses()->create(['area_id' => $this->dubaiArea()->id, 'details' => 'Villa 12']);
        $product = Product::where('name_en', 'Naimi sheep')->first();
        $variant = $product->variants()->where('price', 1191)->first();
        $cut = Option::where('name_en', 'Mandi cut')->first();
        $pack = Option::where('name_en', 'Vacuum packed')->first();

        return array_merge([
            'branch_id' => $product->branch_id,
            'address_id' => $address->id,
            'delivery_slot_id' => DeliverySlot::first()->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'option_ids' => [$cut->id, $pack->id]]],
        ], $overrides);
    }

    public function test_location_resolves_to_nearest_served_area(): void
    {
        $this->getJson('/api/v1/location/resolve?lat=25.22&lng=55.42')
            ->assertOk()->assertJsonPath('served', true)->assertJsonPath('area.name_en', 'Mirdif');

        // Fujairah is not served yet.
        $this->getJson('/api/v1/location/resolve?lat=25.1288&lng=56.3265')->assertOk()->assertJsonPath('served', false);
    }

    public function test_catalog_only_shows_branches_that_deliver_to_the_area(): void
    {
        $this->getJson('/api/v1/products?area_id='.$this->dubaiArea()->id)->assertOk()->assertJsonCount(5, 'data');

        $abuDhabi = Area::where('name_en', 'Yas Island')->first();
        $this->getJson('/api/v1/products?area_id='.$abuDhabi->id)->assertOk()->assertJsonCount(0, 'data');

        // A contracted butcher in Abu Dhabi shows up only there.
        $vendor = Vendor::create(['name_ar' => 'ملحمة', 'name_en' => 'Butcher']);
        $branch = Branch::create(['vendor_id' => $vendor->id, 'emirate_id' => Emirate::where('slug', 'abu-dhabi')->first()->id, 'name_ar' => 'فرع', 'name_en' => 'AD']);
        $branch->areas()->attach($abuDhabi->id, ['delivery_fee' => 25]);
        Product::create(['branch_id' => $branch->id, 'category_id' => Category::first()->id, 'name_ar' => 'نعيمي', 'name_en' => 'AD Naimi', 'price' => 1000]);

        $this->getJson('/api/v1/products?area_id='.$abuDhabi->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.name_en', 'AD Naimi');
        $this->getJson('/api/v1/branches?area_id='.$abuDhabi->id)->assertJsonPath('0.delivery_fee', 25);

        $vendor->update(['active' => false]);
        $this->getJson('/api/v1/products?area_id='.$abuDhabi->id)->assertJsonCount(0, 'data');
    }

    public function test_otp_login_creates_customer_and_returns_token(): void
    {
        $sent = null;
        $this->app->instance(SmsSender::class, new class($sent) implements SmsSender
        {
            public function __construct(public ?string &$sent) {}

            public function sendOtp(string $phone, string $code): void
            {
                $this->sent = $code;
            }
        });

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '050 123 4567'])->assertOk();
        preg_match('/\d{6}/', $sent, $m);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '0501234567', 'code' => '000000'])->assertStatus(422);
        $token = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+971501234567', 'code' => $m[0]])
            ->assertOk()->assertJsonPath('user.phone', '+971501234567')->json('token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('phone', '+971501234567');
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '12345'])->assertStatus(422);
    }

    public function test_order_is_priced_on_the_server_with_options_and_vat(): void
    {
        $user = $this->customer();
        $slot = DeliverySlot::first();

        $res = $this->actingAs($user)->postJson('/api/v1/orders', $this->orderPayload($user, ['purpose' => 'udhiya']))->assertCreated();

        // (1191 for 11-13 kg + 0 mandi + 30 vacuum) * 2 = 2442; VAT 5% = 122.10
        $res->assertJsonPath('order.subtotal', '2442.00')->assertJsonPath('order.vat', '122.10')->assertJsonPath('order.total', '2564.10')
            ->assertJsonPath('order.items.0.variant_ar', '11 - 13 كيلو · 4-6 شهور')
            ->assertJsonPath('order.purpose', 'udhiya')->assertJsonPath('order.number', '100001')->assertJsonCount(2, 'order.items.0.options');
        $this->assertSame(1, $slot->fresh()->booked);

        $id = $res->json('order.id');
        $this->getJson("/api/v1/orders/$id")->assertOk()->assertJsonPath('status', 'pending');
        $this->postJson("/api/v1/orders/$id/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame(0, $slot->fresh()->booked);
    }

    public function test_order_rejects_bad_input(): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $payload = $this->orderPayload($user);

        // Missing required cutting option.
        $p = $payload;
        $p['items'][0]['option_ids'] = [Option::where('name_en', 'Vacuum packed')->first()->id];
        $this->postJson('/api/v1/orders', $p)->assertStatus(422)->assertJsonValidationErrors('items.0.option_ids');

        // Two choices in a single-choice group.
        $p['items'][0]['option_ids'] = Option::whereIn('name_en', ['Mandi cut', 'Kabsa cut', 'Vacuum packed'])->pluck('id')->all();
        $this->postJson('/api/v1/orders', $p)->assertStatus(422);

        // Address in an area the branch doesn't cover.
        $far = $user->addresses()->create(['area_id' => Area::where('name_en', 'Yas Island')->first()->id, 'details' => 'x']);
        $this->postJson('/api/v1/orders', array_merge($payload, ['address_id' => $far->id]))->assertStatus(422)->assertJsonValidationErrors('address_id');

        // Someone else's address.
        $other = User::create(['name' => 'O', 'phone' => '+971509999999']);
        $theirs = $other->addresses()->create(['area_id' => $this->dubaiArea()->id, 'details' => 'y']);
        $this->postJson('/api/v1/orders', array_merge($payload, ['address_id' => $theirs->id]))->assertStatus(422);

        // Full slot.
        $slot = DeliverySlot::first();
        $slot->update(['booked' => $slot->capacity]);
        $this->postJson('/api/v1/orders', $payload)->assertStatus(422)->assertJsonValidationErrors('delivery_slot_id');

        // Aqiqah needs the baby's name.
        $this->postJson('/api/v1/orders', array_merge($payload, ['purpose' => 'aqiqah', 'delivery_slot_id' => null]))->assertStatus(422)->assertJsonValidationErrors('beneficiary_name');

        // Weight not chosen.
        $p = $payload;
        unset($p['items'][0]['variant_id']);
        $p['delivery_slot_id'] = null;
        $this->postJson('/api/v1/orders', $p)->assertStatus(422)->assertJsonValidationErrors('items.0.variant_id');

        // Out of stock.
        Product::where('name_en', 'Naimi sheep')->update(['in_stock' => false]);
        $this->postJson('/api/v1/orders', array_merge($payload, ['delivery_slot_id' => null]))->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_users_cannot_see_other_users_orders(): void
    {
        $user = $this->customer();
        $id = $this->actingAs($user)->postJson('/api/v1/orders', $this->orderPayload($user))->json('order.id');

        $this->actingAs(User::create(['name' => 'O', 'phone' => '+971509999999']))->getJson("/api/v1/orders/$id")->assertNotFound();
    }

    public function test_order_status_transitions_are_enforced(): void
    {
        $user = $this->customer();
        $order = Order::find($this->actingAs($user)->postJson('/api/v1/orders', $this->orderPayload($user))->json('order.id'));

        $order->moveTo('confirmed');
        $this->assertFalse($order->canMoveTo('delivered'));
        $this->expectException(\DomainException::class);
        $order->moveTo('delivered');
    }

    public function test_stripe_webhook_marks_order_paid_only_with_valid_signature(): void
    {
        config(['shop.stripe.webhook_secret' => 'whsec_test']);
        $user = $this->customer();
        $order = Order::find($this->actingAs($user)->postJson('/api/v1/orders', $this->orderPayload($user))->json('order.id'));
        $order->update(['payment_method' => 'card', 'stripe_payment_intent_id' => 'pi_123']);

        $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_123', 'object' => 'payment_intent', 'amount_received' => (int) round($order->total * 100)]]]);
        $t = time();
        $sig = "t=$t,v1=".hash_hmac('sha256', "$t.$payload", 'whsec_test');

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'bad', 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }
}
