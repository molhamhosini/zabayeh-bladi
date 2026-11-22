<?php

namespace App\Filament\Resources\DeliverySlots;

use App\Filament\Concerns\ScopedToBranch;
use App\Filament\Resources\DeliverySlots\Pages\CreateDeliverySlot;
use App\Filament\Resources\DeliverySlots\Pages\EditDeliverySlot;
use App\Filament\Resources\DeliverySlots\Pages\ListDeliverySlots;
use App\Models\DeliverySlot;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Actions\DeleteAction;

class DeliverySlotResource extends Resource
{
    use ScopedToBranch;

    protected static ?string $model = DeliverySlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'موعد توصيل';

    protected static ?string $pluralModelLabel = 'مواعيد التوصيل';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('branch_id')->label('الفرع')->relationship('branch', 'name_ar')->required()->hidden(fn () => static::inBranchPanel()),
            DatePicker::make('date')->label('التاريخ')->required()->minDate(today()),
            TimePicker::make('starts_at')->label('من')->seconds(false)->required(),
            TimePicker::make('ends_at')->label('إلى')->seconds(false)->required()->after('starts_at'),
            TextInput::make('capacity')->label('عدد الطلبات المسموح')->numeric()->minValue(1)->default(10)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch.name_ar')->label('الفرع')->hidden(fn () => static::inBranchPanel()),
                TextColumn::make('date')->label('التاريخ')->date('Y-m-d l')->sortable(),
                TextColumn::make('starts_at')->label('الوقت')->formatStateUsing(fn ($state, DeliverySlot $r) => $r->label()),
                TextColumn::make('booked')->label('المحجوز')->formatStateUsing(fn ($state, DeliverySlot $r) => "{$r->booked} / {$r->capacity}")
                    ->color(fn (DeliverySlot $r) => $r->isFull() ? 'danger' : 'success'),
            ])
            ->modifyQueryUsing(fn ($q) => $q->whereDate('date', '>=', today()))
            ->defaultSort('date')
            ->recordActions([EditAction::make(), DeleteAction::make()->visible(fn (DeliverySlot $r) => $r->booked === 0)]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliverySlots::route('/'),
            'create' => CreateDeliverySlot::route('/create'),
            'edit' => EditDeliverySlot::route('/{record}/edit'),
        ];
    }
}
