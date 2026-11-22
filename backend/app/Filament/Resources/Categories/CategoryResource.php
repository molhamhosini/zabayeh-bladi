<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
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
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'صنف';

    protected static ?string $pluralModelLabel = 'الأصناف';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name_ar')->label('الاسم بالعربية')->required(),
            TextInput::make('name_en')->label('الاسم بالإنجليزية')->required(),
            TextInput::make('slug')->label('المعرّف')->required()->unique(ignoreRecord: true)->alphaDash(),
            TextInput::make('sort')->label('الترتيب')->numeric()->default(0),
            FileUpload::make('image')->label('الصورة')->image()->disk('public')->directory('categories'),
            Toggle::make('active')->label('ظاهر')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->circular(),
                TextColumn::make('name_ar')->label('الصنف')->description(fn (Category $r) => $r->name_en),
                TextColumn::make('products_count')->label('المنتجات')->counts('products'),
                ToggleColumn::make('active')->label('ظاهر'),
            ])
            ->defaultSort('sort')->reorderable('sort')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
