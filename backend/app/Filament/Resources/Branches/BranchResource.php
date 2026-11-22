<?php

namespace App\Filament\Resources\Branches;

use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Filament\Resources\Branches\RelationManagers\AreasRelationManager;
use App\Filament\Resources\Branches\RelationManagers\StaffRelationManager;
use App\Models\Branch;
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

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'فرع';

    protected static ?string $pluralModelLabel = 'الفروع';

    protected static string|\UnitEnum|null $navigationGroup = 'الشركاء';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('vendor_id')->label('الملحمة')->relationship('vendor', 'name_ar')->required()->preload(),
            Select::make('emirate_id')->label('الإمارة')->relationship('emirate', 'name_ar')->required()->preload(),
            TextInput::make('name_ar')->label('اسم الفرع بالعربية')->required(),
            TextInput::make('name_en')->label('اسم الفرع بالإنجليزية')->required(),
            TextInput::make('phone')->label('الهاتف')->tel(),
            TextInput::make('address')->label('العنوان'),
            TextInput::make('lat')->label('خط العرض')->numeric(),
            TextInput::make('lng')->label('خط الطول')->numeric(),
            Toggle::make('accepts_cod')->label('يقبل الدفع عند الاستلام')->default(true),
            Toggle::make('active')->label('مفعّل')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_ar')->label('الفرع')->searchable(['name_ar', 'name_en']),
                TextColumn::make('vendor.name_ar')->label('الملحمة'),
                TextColumn::make('emirate.name_ar')->label('الإمارة')->badge(),
                TextColumn::make('areas_count')->label('مناطق التوصيل')->counts('areas'),
                TextColumn::make('products_count')->label('المنتجات')->counts('products'),
                ToggleColumn::make('active')->label('مفعّل'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [AreasRelationManager::class, StaffRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranches::route('/'),
            'create' => CreateBranch::route('/create'),
            'edit' => EditBranch::route('/{record}/edit'),
        ];
    }
}
