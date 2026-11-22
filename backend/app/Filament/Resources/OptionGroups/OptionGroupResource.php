<?php

namespace App\Filament\Resources\OptionGroups;

use App\Filament\Resources\OptionGroups\Pages\CreateOptionGroup;
use App\Filament\Resources\OptionGroups\Pages\EditOptionGroup;
use App\Filament\Resources\OptionGroups\Pages\ListOptionGroups;
use App\Models\OptionGroup;
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
use Filament\Forms\Components\Repeater;

class OptionGroupResource extends Resource
{
    protected static ?string $model = OptionGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    protected static ?string $modelLabel = 'خيارات التجهيز';

    protected static ?string $pluralModelLabel = 'خيارات التجهيز';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name_ar')->label('اسم المجموعة بالعربية')->required()->placeholder('مثال: التقطيع'),
            TextInput::make('name_en')->label('اسم المجموعة بالإنجليزية')->required(),
            Toggle::make('required')->label('إلزامي'),
            Toggle::make('multiple')->label('يسمح باختيار أكثر من خيار'),
            Repeater::make('options')->label('الخيارات')->relationship()->orderColumn('sort')->columnSpanFull()->columns(4)->schema([
                TextInput::make('name_ar')->label('بالعربية')->required(),
                TextInput::make('name_en')->label('بالإنجليزية')->required(),
                TextInput::make('extra_price')->label('سعر إضافي')->numeric()->default(0)->suffix('درهم'),
                Toggle::make('active')->label('مفعّل')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_ar')->label('المجموعة'),
                TextColumn::make('options.name_ar')->label('الخيارات')->badge(),
                IconColumn::make('required')->label('إلزامي')->boolean(),
                IconColumn::make('multiple')->label('متعدد')->boolean(),
            ])
            ->defaultSort('sort')->reorderable('sort')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOptionGroups::route('/'),
            'create' => CreateOptionGroup::route('/create'),
            'edit' => EditOptionGroup::route('/{record}/edit'),
        ];
    }
}
