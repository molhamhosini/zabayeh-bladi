<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\DeliverySlot;
use App\Models\DeliveryWindow;
use App\Models\Emirate;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function emirate(string $slug): Emirate
    {
        return Emirate::where('slug', $slug)->first();
    }

    public function test_each_emirate_gets_its_own_delivery_times(): void
    {
        $branch = Branch::first(); // delivers to Dubai, Sharjah and Ajman
        $sharjah = $this->emirate('sharjah');
        DeliveryWindow::where('emirate_id', $sharjah->id)->delete();
        DeliveryWindow::create(['emirate_id' => $sharjah->id, 'starts_at' => '16:00:00', 'ends_at' => '20:00:00', 'capacity' => 5]);

        $dubaiArea = Area::where('name_en', 'Mirdif')->first();
        $sharjahArea = Area::where('name_en', 'Al Nahda')->first();

        $dubai = collect($this->getJson("/api/v1/branches/{$branch->id}/slots?area_id={$dubaiArea->id}")->assertOk()->json());
        $shj = collect($this->getJson("/api/v1/branches/{$branch->id}/slots?area_id={$sharjahArea->id}")->assertOk()->json());

        $this->assertEqualsCanonicalizing(['09:00:00', '12:00:00', '15:00:00'], $dubai->where('date', today()->addDay()->toDateString())->pluck('starts_at')->all());
        $this->assertSame(['16:00:00'], $shj->where('date', today()->addDay()->toDateString())->pluck('starts_at')->values()->all());
        $this->assertSame('من 4 عصراً إلى 8 مساءً', $shj->first()['label_ar']);
    }

    public function test_switching_a_window_off_removes_unbooked_slots_but_keeps_booked_ones(): void
    {
        $branch = Branch::first();
        $dubai = $this->emirate('dubai');
        $area = Area::where('name_en', 'Mirdif')->first();
        $morning = DeliveryWindow::where('emirate_id', $dubai->id)->where('starts_at', '09:00:00')->first();

        $booked = DeliverySlot::where('emirate_id', $dubai->id)->where('starts_at', '09:00:00')->whereDate('date', today()->addDays(2))->first();
        $booked->update(['booked' => 1]);

        $morning->update(['active' => false]);
        $slots = collect($this->getJson("/api/v1/branches/{$branch->id}/slots?area_id={$area->id}")->json());

        $this->assertSame([$booked->id], $slots->where('starts_at', '09:00:00')->pluck('id')->values()->all());
        $this->assertDatabaseHas('delivery_slots', ['id' => $booked->id]);
    }

    public function test_order_rejects_a_slot_from_another_emirate(): void
    {
        $user = User::create(['name' => 'T', 'phone' => '+971500000001']);
        $address = $user->addresses()->create(['area_id' => Area::where('name_en', 'Mirdif')->first()->id, 'details' => 'Villa']);
        $product = Product::where('name_en', 'Naimi sheep')->first();
        $sharjahSlot = DeliverySlot::where('emirate_id', $this->emirate('sharjah')->id)->whereDate('date', today()->addDay())->first();

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'branch_id' => $product->branch_id, 'address_id' => $address->id, 'delivery_slot_id' => $sharjahSlot->id, 'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'variant_id' => $product->variants()->first()->id, 'quantity' => 1,
                'option_ids' => [Option::where('name_en', 'Mandi cut')->first()->id, Option::where('name_en', 'Carton (no ice)')->first()->id]]],
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_slot_id');
    }

    public function test_admin_pages_open(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin)->get('/admin/delivery-slots')->assertOk()->assertSee('الإمارة');
        $this->actingAs($admin)->get('/admin/delivery-windows')->assertOk()->assertSee('أبوظبي');
    }

    public function test_branch_staff_can_open_their_delivery_slots(): void
    {
        $staff = User::where('role', 'branch_staff')->first();
        $this->actingAs($staff)->get('/branch/delivery-slots')->assertOk()->assertSee('الإمارة');
    }
}
