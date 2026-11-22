<?php

namespace App\Filament\Resources\Vendors;

use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Models\Vendor;
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

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'ملحمة';

    protected static ?string $pluralModelLabel = 'الملاحم المتعاقدة';

    protected static string|\UnitEnum|null $navigationGroup = 'الشركاء';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name_ar')->label('الاسم بالعربية')->required(),
            TextInput::make('name_en')->label('الاسم بالإنجليزية')->required(),
            TextInput::make('trade_license_no')->label('رقم الرخصة التجارية'),
            TextInput::make('contact_phone')->label('هاتف التواصل')->tel(),
            TextInput::make('commission_rate')->label('عمولة المنصة')->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0),
            FileUpload::make('logo')->label('الشعار')->image()->disk('public')->directory('vendors'),
            Toggle::make('is_own')->label('تابعة لذبائح بلادي'),
            Toggle::make('active')->label('مفعّلة')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')->label('')->disk('public')->circular(),
                TextColumn::make('name_ar')->label('الاسم')->searchable(['name_ar', 'name_en'])->description(fn (Vendor $r) => $r->name_en),
                IconColumn::make('is_own')->label('تابعة لنا')->boolean(),
                TextColumn::make('branches_count')->label('الفروع')->counts('branches'),
                TextColumn::make('commission_rate')->label('العمولة')->suffix('%'),
                ToggleColumn::make('active')->label('مفعّلة'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendors::route('/'),
            'create' => CreateVendor::route('/create'),
            'edit' => EditVendor::route('/{record}/edit'),
        ];
    }
}
