<?php

namespace App\Filament\Resources\DeliveryWindows;

use App\Filament\Resources\DeliveryWindows\Pages\ManageDeliveryWindows;
use App\Models\DeliveryWindow;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

/** Delivery hours per emirate. Upcoming slots for every branch are generated from these. */
class DeliveryWindowResource extends Resource
{
    protected static ?string $model = DeliveryWindow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?string $modelLabel = 'وقت توصيل';

    protected static ?string $pluralModelLabel = 'أوقات التوصيل حسب الإمارة';

    protected static ?string $navigationLabel = 'أوقات التوصيل';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('emirate_id')->label('الإمارة')->relationship('emirate', 'name_ar')->required(),
            TimePicker::make('starts_at')->label('من')->seconds(false)->required(),
            TimePicker::make('ends_at')->label('إلى')->seconds(false)->required()->after('starts_at'),
            TextInput::make('capacity')->label('عدد الطلبات في كل يوم لهذه الفترة')->numeric()->minValue(1)->default(10)->required(),
            Toggle::make('active')->label('مفعّل')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('عند التعديل تتحدث المواعيد القادمة تلقائياً. المواعيد المحجوزة لا تتغير.')
            ->defaultGroup(Group::make('emirate.name_ar')->label('الإمارة')->collapsible())
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')->label('الفترة')->formatStateUsing(fn ($state, DeliveryWindow $r) => $r->label()),
                TextColumn::make('capacity')->label('عدد الطلبات'),
                ToggleColumn::make('active')->label('مفعّل'),
            ])
            ->filters([
                SelectFilter::make('emirate_id')->label('الإمارة')->relationship('emirate', 'name_ar'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label('إضافة وقت')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliveryWindows::route('/')];
    }
}
