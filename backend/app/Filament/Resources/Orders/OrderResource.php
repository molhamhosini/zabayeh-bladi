<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Concerns\ScopedToBranch;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    use ScopedToBranch;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'طلب';

    protected static ?string $pluralModelLabel = 'الطلبات';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 1;

    public const STATUSES = [
        'pending' => 'جديد', 'confirmed' => 'مؤكد', 'slaughtering' => 'قيد الذبح', 'packing' => 'قيد التجهيز',
        'out_for_delivery' => 'في الطريق', 'delivered' => 'تم التوصيل', 'cancelled' => 'ملغي',
    ];

    public const STATUS_ACTIONS = [
        'confirmed' => 'تأكيد الطلب', 'slaughtering' => 'بدء الذبح', 'packing' => 'بدء التجهيز',
        'out_for_delivery' => 'خرج للتوصيل', 'delivered' => 'تم التوصيل', 'cancelled' => 'إلغاء الطلب',
    ];

    public const STATUS_COLORS = [
        'pending' => 'warning', 'confirmed' => 'info', 'slaughtering' => 'info', 'packing' => 'info',
        'out_for_delivery' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger',
    ];

    public const PURPOSES = ['normal' => 'عادي', 'udhiya' => 'أضحية', 'aqiqah' => 'عقيقة', 'sadaqah' => 'صدقة'];

    public const PAYMENT = ['unpaid' => 'غير مدفوع', 'paid' => 'مدفوع', 'refunded' => 'مسترد', 'failed' => 'فشل الدفع'];

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('status', 'pending')->count();

        return $n ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false; // Orders are placed from the customer app.
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الطلب')->columns(3)->schema([
                TextEntry::make('number')->label('رقم الطلب')->copyable(),
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('created_at')->label('تاريخ الطلب')->dateTime('Y-m-d H:i'),
                TextEntry::make('purpose')->label('نوع الطلب')->formatStateUsing(fn ($state) => self::PURPOSES[$state] ?? $state),
                TextEntry::make('beneficiary_name')->label('اسم صاحب الأضحية / المولود')->placeholder('-'),
                TextEntry::make('donate_to_charity')->label('توزيع للجمعيات')->formatStateUsing(fn ($state) => $state ? 'نعم' : 'لا'),
                TextEntry::make('branch.name_ar')->label('الفرع'),
                TextEntry::make('driver.name')->label('السائق')->placeholder('لم يُعيّن'),
                TextEntry::make('notes')->label('ملاحظات العميل')->placeholder('-'),
            ]),
            Section::make('العميل والتوصيل')->columns(3)->schema([
                TextEntry::make('user.name')->label('العميل'),
                TextEntry::make('user.phone')->label('الجوال')->url(fn (Order $r) => 'tel:'.$r->user?->phone),
                TextEntry::make('address.area.name_ar')->label('المنطقة')
                    ->formatStateUsing(fn ($state, Order $r) => $r->address->area->emirate->name_ar.' - '.$state),
                TextEntry::make('address.details')->label('العنوان')->columnSpan(2),
                TextEntry::make('slot')->label('موعد التوصيل')->placeholder('أقرب وقت')
                    ->formatStateUsing(fn ($state, Order $r) => $r->slot ? $r->slot->date->format('Y-m-d').'  '.substr($r->slot->starts_at, 0, 5).' - '.substr($r->slot->ends_at, 0, 5) : null),
                TextEntry::make('map')->label('الموقع على الخريطة')->state(fn (Order $r) => $r->address->lat ? 'فتح في خرائط Google' : null)->placeholder('-')
                    ->url(fn (Order $r) => $r->address->lat ? "https://maps.google.com/?q={$r->address->lat},{$r->address->lng}" : null, true),
            ]),
            Section::make('المنتجات')->schema([
                RepeatableEntry::make('items')->label('')->columns(4)->schema([
                    TextEntry::make('name_ar')->label('المنتج'),
                    TextEntry::make('quantity')->label('الكمية'),
                    TextEntry::make('options')->label('التجهيز')->listWithLineBreaks()
                        ->state(fn ($record) => collect($record->options)->map(fn ($o) => "{$o['group_ar']}: {$o['name_ar']}")->all()),
                    TextEntry::make('line_total')->label('المجموع')->money('AED'),
                ]),
            ]),
            Section::make('الدفع')->columns(4)->schema([
                TextEntry::make('payment_method')->label('طريقة الدفع')->formatStateUsing(fn ($state) => $state === 'card' ? 'بطاقة (Stripe)' : 'عند الاستلام'),
                TextEntry::make('payment_status')->label('حالة الدفع')->badge()
                    ->formatStateUsing(fn ($state) => self::PAYMENT[$state] ?? $state)->color(fn ($state) => $state === 'paid' ? 'success' : 'warning'),
                TextEntry::make('subtotal')->label('المنتجات')->money('AED'),
                TextEntry::make('delivery_fee')->label('التوصيل')->money('AED'),
                TextEntry::make('vat')->label('الضريبة 5%')->money('AED'),
                TextEntry::make('total')->label('الإجمالي')->money('AED')->weight('bold'),
            ]),
            Section::make('سجل الحالة')->collapsed()->schema([
                RepeatableEntry::make('statusLogs')->label('')->columns(3)->schema([
                    TextEntry::make('status')->label('الحالة')->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state),
                    TextEntry::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i'),
                    TextEntry::make('note')->label('ملاحظة')->placeholder('-'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('رقم الطلب')->searchable(),
                TextColumn::make('user.name')->label('العميل')->searchable()->description(fn (Order $r) => $r->user?->phone),
                TextColumn::make('branch.name_ar')->label('الفرع')->hidden(fn () => static::inBranchPanel()),
                TextColumn::make('purpose')->label('النوع')->badge()->formatStateUsing(fn ($state) => self::PURPOSES[$state] ?? $state),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('slot.date')->label('موعد التوصيل')->date('Y-m-d')->placeholder('أقرب وقت')->sortable(),
                TextColumn::make('payment_status')->label('الدفع')->badge()
                    ->formatStateUsing(fn ($state, Order $r) => (self::PAYMENT[$state] ?? $state).' - '.($r->payment_method === 'card' ? 'بطاقة' : 'نقداً'))
                    ->color(fn ($state) => $state === 'paid' ? 'success' : 'warning'),
                TextColumn::make('total')->label('الإجمالي')->money('AED')->sortable(),
                TextColumn::make('created_at')->label('وقت الطلب')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(self::STATUSES)->multiple(),
                SelectFilter::make('purpose')->label('النوع')->options(self::PURPOSES),
                SelectFilter::make('branch_id')->label('الفرع')->relationship('branch', 'name_ar')->hidden(fn () => static::inBranchPanel()),
                Filter::make('today')->label('توصيل اليوم')->query(fn (Builder $q) => $q->whereHas('slot', fn ($s) => $s->whereDate('date', today()))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
