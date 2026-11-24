<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Exports\OrdersExcel;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Widgets\OrderStatusCards;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListOrders extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = OrderResource::class;

    protected function getHeaderWidgets(): array
    {
        return [OrderStatusCards::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->modalHeading('تصدير الطلبات إلى Excel')
                ->modalDescription('ملف للمحاسب: ورقة بالطلبات ومبالغ الفاتورة، وورقة بالمنتجات. تاريخ الطلب حسب توقيت الإمارات.')
                ->modalSubmitActionLabel('تنزيل الملف')
                ->schema([
                    DatePicker::make('from')->label('من تاريخ الطلب')->default(now('Asia/Dubai')->startOfMonth())->required(),
                    DatePicker::make('until')->label('إلى تاريخ الطلب')->default(now('Asia/Dubai'))->required()->afterOrEqual('from'),
                    Toggle::make('use_filters')->label('تطبيق الفلاتر والبحث الحالية في الجدول')->default(false),
                ])
                ->action(function (array $data): BinaryFileResponse {
                    $from = Carbon::parse($data['from'], 'Asia/Dubai')->startOfDay()->utc();
                    $until = Carbon::parse($data['until'], 'Asia/Dubai')->endOfDay()->utc();
                    $query = ($data['use_filters'] ?? false)
                        ? $this->getFilteredTableQuery()->reorder()
                        : OrderResource::getEloquentQuery();
                    $query->whereBetween('orders.created_at', [$from, $until]);

                    $path = tempnam(sys_get_temp_dir(), 'orders').'.xlsx';
                    OrdersExcel::write($query, $path);

                    return response()->download($path, "orders_{$data['from']}_{$data['until']}.xlsx")->deleteFileAfterSend();
                }),
        ];
    }
}
