<?php

namespace App\Filament\Resources\Products;

use App\Filament\Concerns\ScopedToBranch;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    use ScopedToBranch;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $modelLabel = 'منتج';

    protected static ?string $pluralModelLabel = 'المنتجات';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المنتج')->columns(2)->columnSpanFull()->schema([
                Select::make('branch_id')->label('الفرع')->relationship('branch', 'name_ar')->required()->searchable()->preload()
                    ->hidden(fn () => static::inBranchPanel()),
                Select::make('category_id')->label('الصنف')->relationship('category', 'name_ar')->required()->preload(),
                TextInput::make('name_ar')->label('الاسم بالعربية')->required(),
                TextInput::make('name_en')->label('الاسم بالإنجليزية')->required(),
                Textarea::make('description_ar')->label('الوصف بالعربية'),
                Textarea::make('description_en')->label('الوصف بالإنجليزية'),
                TextInput::make('weight_min_kg')->label('أقل وزن (كغ)')->numeric(),
                TextInput::make('weight_max_kg')->label('أعلى وزن (كغ)')->numeric(),
                TextInput::make('price')->label('السعر قبل الضريبة')->required()->numeric()->minValue(0)->suffix('درهم'),
                TextInput::make('compare_at_price')->label('السعر قبل الخصم (اختياري)')->numeric()->minValue(0)->suffix('درهم')
                    ->helperText('إذا كان أعلى من السعر يظهر للعميل كخصم.')->gt('price'),
                TextInput::make('sort')->label('الترتيب')->numeric()->default(0),
                FileUpload::make('image')->label('الصورة')->image()->disk('public')->directory('products')->columnSpanFull(),
                Toggle::make('in_stock')->label('متوفر')->default(true),
                Toggle::make('active')->label('ظاهر في التطبيق')->default(true),
                Toggle::make('is_featured')->label('يظهر في عروض الرئيسية'),
            ]),
            Section::make('الأوزان المتاحة')->columnSpanFull()
                ->description('أضف وزناً أو أكثر بالعمر والسعر. سعر المنتج في القائمة يصبح أقل سعر من هذه الأوزان.')
                ->schema([
                    Repeater::make('variants')->hiddenLabel()->relationship()->orderColumn('sort')->columns(6)->defaultItems(0)
                        ->addActionLabel('إضافة وزن')
                        ->schema([
                            TextInput::make('weight_min_kg')->label('من (كغ)')->numeric()->required(),
                            TextInput::make('weight_max_kg')->label('إلى (كغ)')->numeric()->required()->gte('weight_min_kg'),
                            TextInput::make('age_ar')->label('العمر')->placeholder('2-4 شهور'),
                            TextInput::make('price')->label('السعر')->numeric()->required()->suffix('درهم'),
                            TextInput::make('compare_at_price')->label('قبل الخصم')->numeric()->suffix('درهم'),
                            Toggle::make('in_stock')->label('متوفر')->default(true)->inline(false),
                        ]),
                ]),
            Section::make('خيارات التجهيز')->columnSpanFull()->schema([
                CheckboxList::make('optionGroups')->label('مجموعات الخيارات المتاحة لهذا المنتج')
                    ->relationship('optionGroups', 'name_ar')->columns(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->circular(),
                TextColumn::make('name_ar')->label('المنتج')->searchable(['name_ar', 'name_en'])->description(fn (Product $r) => $r->name_en),
                TextColumn::make('category.name_ar')->label('الصنف')->badge(),
                TextColumn::make('branch.name_ar')->label('الفرع')->hidden(fn () => static::inBranchPanel()),
                TextColumn::make('weight')->label('الوزن')->state(fn (Product $r) => $r->weight_min_kg ? "{$r->weight_min_kg} - {$r->weight_max_kg} كغ" : '-'),
                TextColumn::make('price')->label('السعر')->money('AED')->sortable(),
                ToggleColumn::make('in_stock')->label('متوفر'),
                ToggleColumn::make('active')->label('ظاهر'),
                ToggleColumn::make('is_featured')->label('عرض'),
            ])
            ->defaultSort('sort')
            ->filters([
                SelectFilter::make('category_id')->label('الصنف')->relationship('category', 'name_ar'),
                SelectFilter::make('branch_id')->label('الفرع')->relationship('branch', 'name_ar')->hidden(fn () => static::inBranchPanel()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
