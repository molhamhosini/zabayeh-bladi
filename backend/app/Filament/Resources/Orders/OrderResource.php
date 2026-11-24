<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Concerns\ScopedToBranch;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
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
        'returned' => 'مرتجع', 'failed_delivery' => 'فشل التوصيل',
    ];

    /** Icon per status, used on the status cards. */
    public const STATUS_ICONS = [
        'pending' => 'heroicon-o-clock', 'confirmed' => 'heroicon-o-check-circle', 'slaughtering' => 'heroicon-o-scissors',
        'packing' => 'heroicon-o-archive-box', 'out_for_delivery' => 'heroicon-o-truck', 'delivered' => 'heroicon-o-home-modern',
        'cancelled' => 'heroicon-o-x-circle', 'returned' => 'heroicon-o-arrow-uturn-left', 'failed_delivery' => 'heroicon-o-exclamation-triangle',
    ];

    public const STATUS_ACTIONS = [
        'confirmed' => 'تأكيد الطلب', 'slaughtering' => 'بدء الذبح', 'packing' => 'بدء التجهيز',
        'out_for_delivery' => 'خرج للتوصيل', 'delivered' => 'تم التوصيل', 'cancelled' => 'إلغاء الطلب',
        'returned' => 'تسجيل مرتجع', 'failed_delivery' => 'فشل التوصيل',
    ];

    public const STATUS_COLORS = [
        'pending' => 'warning', 'confirmed' => 'success', 'slaughtering' => 'info', 'packing' => 'info',
        'out_for_delivery' => 'info', 'delivered' => 'gray', 'cancelled' => 'danger',
        'returned' => 'warning', 'failed_delivery' => 'danger',
    ];

    public const PURPOSES = ['normal' => 'عادي', 'udhiya' => 'أضحية', 'aqiqah' => 'عقيقة', 'sadaqah' => 'صدقة'];

    public const METHODS = ['cod' => 'كاش', 'card' => 'Stripe', 'tabby' => 'تابي', 'tamara' => 'تمارا'];

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
                    ->formatStateUsing(fn ($state, Order $r) => $r->slot ? $r->slot->date->format('Y-m-d').' - '.$r->slot->label() : null),
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
                TextEntry::make('payment_method')->label('طريقة الدفع')->formatStateUsing(fn ($state) => self::METHODS[$state] ?? $state),
                TextEntry::make('payment_reference')->label('مرجع الدفع (تابي / تمارا)')->placeholder('-')->copyable(),
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
                TextColumn::make('slot.date')->label('موعد التوصيل')->date('Y-m-d')->placeholder('أقرب وقت')->sortable()
                    ->description(fn (Order $r) => $r->slot?->label()),
                TextColumn::make('payment_status')->label('الدفع')->badge()
                    ->formatStateUsing(fn ($state, Order $r) => (self::PAYMENT[$state] ?? $state).' - '.(self::METHODS[$r->payment_method] ?? $r->payment_method))
                    ->color(fn ($state) => $state === 'paid' ? 'success' : 'warning'),
                TextColumn::make('total')->label('الإجمالي')->money('AED')->sortable(),
                TextColumn::make('created_at')->label('وقت الطلب')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (Order $r) => 'zb-st zb-st-'.$r->status)
            ->poll('30s')
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(self::STATUSES)->multiple(),
                SelectFilter::make('payment_method')->label('طريقة الدفع')->options(self::METHODS),
                SelectFilter::make('payment_status')->label('حالة الدفع')->options(self::PAYMENT),
                SelectFilter::make('purpose')->label('النوع')->options(self::PURPOSES),
                SelectFilter::make('branch_id')->label('الفرع')->relationship('branch', 'name_ar')->hidden(fn () => static::inBranchPanel()),
                Filter::make('delivery_date')
                    ->label('تاريخ التوصيل')
                    ->schema([
                        DatePicker::make('from')->label('من تاريخ'),
                        DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    ->query(fn (Builder $q, array $data) => static::applyDeliveryDate($q, $data))
                    ->indicateUsing(fn (array $data) => array_filter([
                        $data['from'] ?? null ? 'من '.$data['from'] : null,
                        $data['until'] ?? null ? 'إلى '.$data['until'] : null,
                    ])),
                Filter::make('today')->label('توصيل اليوم')->query(fn (Builder $q) => $q->whereHas('slot', fn ($s) => $s->whereDate('date', today()))),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('invoice')->label('فاتورة')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (Order $record) => route('orders.invoice', [$record, 'print' => 1]))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function applyDeliveryDate(Builder $q, array $data): Builder
    {
        if (empty($data['from']) && empty($data['until'])) {
            return $q;
        }

        return $q->whereHas('slot', fn (Builder $s) => $s
            ->when($data['from'] ?? null, fn ($s, $d) => $s->whereDate('date', '>=', $d))
            ->when($data['until'] ?? null, fn ($s, $d) => $s->whereDate('date', '<=', $d)));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
