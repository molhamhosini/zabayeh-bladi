<?php

namespace App\Filament\Resources\Banners;

use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Models\Banner;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'إعلان';

    protected static ?string $pluralModelLabel = 'إعلانات الرئيسية';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            FileUpload::make('image')->label('صورة الإعلان (عرض 1200 × 600)')->image()->disk('public')->directory('banners')
                ->imageAspectRatio('2:1')->columnSpanFull()
                ->helperText('إن لم تُرفع صورة يظهر الإعلان بخلفية الهوية مع العنوان.'),
            TextInput::make('title_ar')->label('العنوان بالعربية'),
            TextInput::make('title_en')->label('العنوان بالإنجليزية'),
            TextInput::make('subtitle_ar')->label('النص الفرعي بالعربية'),
            TextInput::make('subtitle_en')->label('النص الفرعي بالإنجليزية'),
            Select::make('category_id')->label('عند الضغط يفتح قسم')->relationship('category', 'name_ar')->placeholder('لا شيء'),
            TextInput::make('sort')->label('الترتيب')->numeric()->default(0),
            DatePicker::make('starts_on')->label('يبدأ من'),
            DatePicker::make('ends_on')->label('ينتهي في'),
            Toggle::make('active')->label('ظاهر')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->disk('public'),
                TextColumn::make('title_ar')->label('العنوان')->description(fn (Banner $r) => $r->subtitle_ar),
                TextColumn::make('category.name_ar')->label('القسم')->placeholder('-'),
                TextColumn::make('ends_on')->label('ينتهي')->date('Y-m-d')->placeholder('دائم'),
                ToggleColumn::make('active')->label('ظاهر'),
            ])
            ->defaultSort('sort')->reorderable('sort')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
