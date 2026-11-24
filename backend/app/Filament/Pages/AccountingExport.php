<?php

namespace App\Filament\Pages;

use App\Exports\OrdersExcel;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Standalone page for the accountant: pick a period and download the orders as Excel. */
class AccountingExport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'تصدير للمحاسب';

    protected static ?string $title = 'تصدير الطلبات إلى Excel';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => now('Asia/Dubai')->startOfMonth()->toDateString(),
            'until' => now('Asia/Dubai')->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('الفترة والفلاتر')
                ->description('الملف فيه ورقتان: "الطلبات" بمبالغ الفاتورة لكل طلب (المجموع، التوصيل، الخاضع للضريبة، الضريبة 5%، الإجمالي) مع صف الإجمالي، و"المنتجات" بكل بند. الطلبات الملغاة والمرتجعة تظهر لكنها لا تدخل في الإجمالي.')
                ->schema([
                    Grid::make(2)->schema([
                        DatePicker::make('from')->label('من تاريخ الطلب')->required(),
                        DatePicker::make('until')->label('إلى تاريخ الطلب')->required()->afterOrEqual('from'),
                        Select::make('branch_id')->label('الفرع')->placeholder('كل الفروع')
                            ->options(fn () => Branch::pluck('name_ar', 'id')),
                        Select::make('payment_status')->label('حالة الدفع')->placeholder('الكل')->options(OrderResource::PAYMENT),
                        Select::make('payment_method')->label('طريقة الدفع')->placeholder('الكل')->options(OrderResource::METHODS),
                        Select::make('status')->label('حالة الطلب')->placeholder('الكل')->options(OrderResource::STATUSES)->multiple(),
                    ]),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('download')
                ->footer([Actions::make([
                    Action::make('download')->label('تنزيل ملف Excel')->icon('heroicon-o-arrow-down-tray')->submit('download'),
                ])]),
        ]);
    }

    public function download(): BinaryFileResponse
    {
        $d = $this->form->getState();
        $path = tempnam(sys_get_temp_dir(), 'orders').'.xlsx';
        OrdersExcel::write(static::query($d), $path);

        return response()->download($path, "orders_{$d['from']}_{$d['until']}.xlsx")->deleteFileAfterSend();
    }

    /** @param array<string, mixed> $d */
    public static function query(array $d): Builder
    {
        return Order::query()
            ->whereBetween('created_at', [
                Carbon::parse($d['from'], 'Asia/Dubai')->startOfDay()->utc(),
                Carbon::parse($d['until'], 'Asia/Dubai')->endOfDay()->utc(),
            ])
            ->when($d['branch_id'] ?? null, fn (Builder $q, $v) => $q->where('branch_id', $v))
            ->when($d['payment_status'] ?? null, fn (Builder $q, $v) => $q->where('payment_status', $v))
            ->when($d['payment_method'] ?? null, fn (Builder $q, $v) => $q->where('payment_method', $v))
            ->when($d['status'] ?? null, fn (Builder $q, $v) => $q->whereIn('status', $v));
    }
}
