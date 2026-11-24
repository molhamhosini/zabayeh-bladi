<?php

namespace Tests\Feature;

use App\Exports\OrdersExcel;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Address;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class OrdersExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_excel_has_one_row_per_order_and_a_totals_row(): void
    {
        $customer = User::create(['name' => 'Salem', 'phone' => '+971500000001', 'role' => 'customer']);
        $branch = Branch::first();
        $address = Address::create(['user_id' => $customer->id, 'area_id' => $branch->areas()->first()->id, 'details' => 'Villa 3']);
        $product = Product::where('branch_id', $branch->id)->first();
        $required = $product->optionGroups()->where('required', true)->with('options')->get()->map(fn ($g) => $g->options->first()->id)->all();
        foreach ([1, 2] as $_) {
            $this->actingAs($customer)->postJson('/api/v1/orders', [
                'branch_id' => $branch->id, 'address_id' => $address->id, 'payment_method' => 'cod',
                'items' => [['product_id' => $product->id, 'variant_id' => $product->variants()->first()?->id, 'quantity' => 1, 'option_ids' => $required]],
            ])->assertCreated();
        }
        Order::latest('id')->first()->update(['status' => 'cancelled']);

        $path = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        $this->assertSame(2, OrdersExcel::write(OrderResource::getModel()::query(), $path));

        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheets[$sheet->getName()] = $rows;
        }
        $reader->close();

        $orders = $sheets['الطلبات'];
        $this->assertSame('رقم الفاتورة', $orders[0][0]);
        $this->assertCount(4, $orders); // header + 2 orders + totals
        $first = Order::orderBy('id')->first();
        $this->assertEqualsWithDelta((float) $first->total, end($orders)[19], 0.01); // cancelled one left out
        $this->assertCount(3, $sheets['المنتجات']);
    }

    public function test_admin_orders_page_shows_export_button(): void
    {
        $this->actingAs(User::where('role', 'admin')->first(), 'web')->get('/admin/orders')->assertOk()->assertSee('تصدير Excel');
    }

    public function test_accountant_page_downloads_excel(): void
    {
        $this->actingAs(User::where('role', 'admin')->first(), 'web')->get('/admin/accounting-export')->assertOk()->assertSee('تنزيل ملف Excel');
        \Livewire\Livewire::test(\App\Filament\Pages\AccountingExport::class)->call('download')->assertFileDownloaded();
    }
}
