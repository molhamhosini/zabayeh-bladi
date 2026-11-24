<?php

namespace App\Exports;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Accountant export: sheet 1 = one row per order (invoice amounts), sheet 2 = one row per product line.
 * Cancelled orders are listed but left out of the totals row.
 */
class OrdersExcel
{
    public const ORDER_HEADERS = [
        'رقم الفاتورة', 'رقم الطلب', 'تاريخ الطلب', 'تاريخ التوصيل', 'موعد التوصيل', 'الفرع', 'العميل', 'الجوال',
        'الإمارة', 'المنطقة', 'نوع الطلب', 'الحالة', 'طريقة الدفع', 'حالة الدفع', 'مرجع الدفع',
        'المجموع', 'التوصيل', 'الخاضع للضريبة', 'الضريبة 5%', 'الإجمالي',
    ];

    public const ITEM_HEADERS = [
        'رقم الفاتورة', 'تاريخ الطلب', 'الفرع', 'الحالة', 'المنتج', 'الوزن', 'خيارات التجهيز',
        'الكمية', 'سعر الوحدة', 'المبلغ الخاضع', 'الضريبة 5%', 'الإجمالي',
    ];

    public static function write(Builder $query, string $path): int
    {
        $options = new Options;
        $options->DEFAULT_COLUMN_WIDTH = 16;
        $options->setColumnWidth(30, 6, 7);
        $writer = new Writer($options);
        $writer->openToFile($path);
        $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('4A0E17');
        $bold = (new Style)->setFontBold()->setBackgroundColor('F3E7CC');

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('الطلبات');
        $sheet->setSheetView((new \OpenSpout\Writer\XLSX\Entity\SheetView)->setRightToLeft(true)->setFreezeRow(2));
        $writer->addRow(Row::fromValues(self::ORDER_HEADERS, $header));

        $count = 0;
        $sum = ['subtotal' => 0.0, 'delivery_fee' => 0.0, 'taxable' => 0.0, 'vat' => 0.0, 'total' => 0.0];
        $items = [];
        $query->with('items', 'user', 'branch', 'address.area.emirate', 'slot')->orderBy('id')->chunk(500, function ($orders) use ($writer, &$count, &$sum, &$items) {
            foreach ($orders as $o) {
                /** @var Order $o */
                $taxable = round((float) $o->subtotal + (float) $o->delivery_fee, 2);
                $writer->addRow(Row::fromValues([
                    'INV-'.$o->number, $o->number,
                    $o->created_at->timezone('Asia/Dubai')->format('Y-m-d H:i'),
                    $o->slot?->date?->format('Y-m-d') ?? '', $o->slot?->label() ?? '',
                    $o->branch?->name_ar ?? '', $o->user?->name ?? '', $o->user?->phone ?? '',
                    $o->address?->area?->emirate?->name_ar ?? '', $o->address?->area?->name_ar ?? '',
                    OrderResource::PURPOSES[$o->purpose] ?? $o->purpose,
                    OrderResource::STATUSES[$o->status] ?? $o->status,
                    OrderResource::METHODS[$o->payment_method] ?? $o->payment_method,
                    OrderResource::PAYMENT[$o->payment_status] ?? $o->payment_status,
                    $o->payment_reference ?? '',
                    (float) $o->subtotal, (float) $o->delivery_fee, $taxable, (float) $o->vat, (float) $o->total,
                ]));
                $count++;
                if (! in_array($o->status, ['cancelled', 'returned', 'failed_delivery'], true)) {
                    $sum['subtotal'] += (float) $o->subtotal;
                    $sum['delivery_fee'] += (float) $o->delivery_fee;
                    $sum['taxable'] += $taxable;
                    $sum['vat'] += (float) $o->vat;
                    $sum['total'] += (float) $o->total;
                }
                foreach ($o->items as $it) {
                    $line = (float) $it->line_total;
                    $vat = round($line * 0.05, 2);
                    $items[] = [
                        'INV-'.$o->number, $o->created_at->timezone('Asia/Dubai')->format('Y-m-d'), $o->branch?->name_ar ?? '',
                        OrderResource::STATUSES[$o->status] ?? $o->status,
                        $it->name_ar, $it->variant_ar ?? '',
                        collect($it->options ?? [])->map(fn ($x) => ($x['group_ar'] ?? '').': '.($x['name_ar'] ?? ''))->join(' | '),
                        (int) $it->quantity, (float) $it->unit_price, $line, $vat, round($line + $vat, 2),
                    ];
                }
            }
        });

        $writer->addRow(Row::fromValues(array_merge(
            ['الإجمالي (بدون الملغي والمرتجع)', $count.' طلب'], array_fill(0, 13, ''),
            array_map(fn ($v) => round($v, 2), array_values($sum)),
        ), $bold));

        $writer->addNewSheetAndMakeItCurrent()->setName('المنتجات');
        $writer->getCurrentSheet()->setSheetView((new \OpenSpout\Writer\XLSX\Entity\SheetView)->setRightToLeft(true)->setFreezeRow(2));
        $writer->addRow(Row::fromValues(self::ITEM_HEADERS, $header));
        foreach ($items as $r) {
            $writer->addRow(Row::fromValues($r));
        }
        $writer->close();

        return $count;
    }
}
