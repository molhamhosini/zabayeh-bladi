<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\InvoiceCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function order(): Order
    {
        $customer = User::create(['name' => 'Salem', 'phone' => '+971500000001', 'role' => 'customer']);
        $branch = Branch::first();
        $area = $branch->areas()->first();
        $address = Address::create(['user_id' => $customer->id, 'area_id' => $area->id, 'details' => 'Villa 3']);
        $product = Product::where('branch_id', $branch->id)->whereDoesntHave('variants')->whereDoesntHave('optionGroups', fn ($q) => $q->where('required', true))->first()
            ?? Product::where('branch_id', $branch->id)->first();
        $variant = $product->variants()->first();
        $required = $product->optionGroups()->where('required', true)->with('options')->get()->map(fn ($g) => $g->options->first()->id)->all();

        $res = $this->actingAs($customer)->postJson('/api/v1/orders', [
            'branch_id' => $branch->id, 'address_id' => $address->id, 'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'variant_id' => $variant?->id, 'quantity' => 1, 'option_ids' => $required]],
        ])->assertCreated();
        $this->app['auth']->forgetGuards();

        return Order::find($res->json('order.id'));
    }

    public function test_admin_prints_a_tax_invoice_with_trn(): void
    {
        $order = $this->order();
        InvoiceCompany::put(['name_ar' => 'ذبائح بلادي', 'trn' => '100123456700003']);

        $this->actingAs(User::where('role', 'admin')->first(), 'web')
            ->get("/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertSee('فاتورة ضريبية')
            ->assertSee('100123456700003')
            ->assertSee('نور الهدى لتجارة المواشي والاغنام ذ.م.م')
            ->assertSee('INV-'.$order->number)
            ->assertSee(number_format((float) $order->total, 2));

        $this->get("/orders/{$order->id}/invoice?size=80mm")->assertOk()->assertSee('TAX INVOICE');
        $this->get('/admin/invoice-settings')->assertOk();
        $this->get("/admin/orders/{$order->id}")->assertOk()->assertSee('طباعة الفاتورة');
    }

    public function test_only_staff_of_the_order_branch_can_print(): void
    {
        $order = $this->order();
        $this->get("/orders/{$order->id}/invoice")->assertRedirect('/admin/login');

        $customer = $order->user;
        $this->actingAs($customer, 'web')->get("/orders/{$order->id}/invoice")->assertForbidden();

        $staff = User::where('role', 'branch_staff')->first();
        $this->actingAs($staff, 'web')->get("/orders/{$order->id}/invoice")
            ->assertStatus($staff->branch_id === $order->branch_id ? 200 : 403);
    }
}
