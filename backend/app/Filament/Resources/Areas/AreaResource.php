<?php

namespace App\Filament\Resources\Areas;

use App\Filament\Resources\Areas\Pages\CreateArea;
use App\Filament\Resources\Areas\Pages\EditArea;
use App\Filament\Resources\Areas\Pages\ListAreas;
use App\Models\Area;
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
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;

class AreaResource extends Resource
{
    protected static ?string $model = Area::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $modelLabel = 'منطقة';

    protected static ?string $pluralModelLabel = 'المناطق';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('emirate_id')->label('الإمارة')->relationship('emirate', 'name_ar')->required()->preload(),
            TextInput::make('name_ar')->label('الاسم بالعربية')->required(),
            TextInput::make('name_en')->label('الاسم بالإنجليزية')->required(),
            TextInput::make('lat')->label('خط العرض (مركز المنطقة)')->numeric()->required(),
            TextInput::make('lng')->label('خط الطول (مركز المنطقة)')->numeric()->required(),
            Toggle::make('active')->label('مفعّلة')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('emirate.name_ar')->label('الإمارة')->badge()->sortable(),
                TextColumn::make('name_ar')->label('المنطقة')->searchable(['name_ar', 'name_en'])->description(fn (Area $r) => $r->name_en),
                TextColumn::make('branches_count')->label('فروع توصل لها')->counts('branches'),
                ToggleColumn::make('active')->label('مفعّلة'),
            ])
            ->filters([SelectFilter::make('emirate_id')->label('الإمارة')->relationship('emirate', 'name_ar')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAreas::route('/'),
            'create' => CreateArea::route('/create'),
            'edit' => EditArea::route('/{record}/edit'),
        ];
    }
}
