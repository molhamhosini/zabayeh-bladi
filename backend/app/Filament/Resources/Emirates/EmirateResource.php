<?php

namespace App\Filament\Resources\Emirates;

use App\Filament\Resources\Emirates\Pages\ManageEmirates;
use App\Models\Emirate;
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

class EmirateResource extends Resource
{
    protected static ?string $model = Emirate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;

    protected static ?string $modelLabel = 'إمارة';

    protected static ?string $pluralModelLabel = 'الإمارات';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_ar')->label('الاسم بالعربية')->required(),
            TextInput::make('name_en')->label('الاسم بالإنجليزية')->required(),
            TextInput::make('slug')->label('المعرّف')->required()->unique(ignoreRecord: true)->alphaDash(),
            Toggle::make('active')->label('مفعّلة (نوصل لها)')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_ar')->label('الإمارة')->description(fn (Emirate $r) => $r->name_en),
                TextColumn::make('areas_count')->label('المناطق')->counts('areas'),
                ToggleColumn::make('active')->label('مفعّلة'),
            ])
            ->defaultSort('sort')->reorderable('sort')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageEmirates::route('/')];
    }
}
