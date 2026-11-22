<?php

namespace App\Filament\Resources\Branches\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AreasRelationManager extends RelationManager
{
    protected static string $relationship = 'areas';

    protected static ?string $title = 'مناطق التوصيل';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('delivery_fee')->label('رسوم التوصيل')->numeric()->minValue(0)->default(0)->suffix('درهم')->required(),
            TextInput::make('min_order')->label('الحد الأدنى للطلب')->numeric()->minValue(0)->default(0)->suffix('درهم')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_ar')
            ->columns([
                TextColumn::make('emirate.name_ar')->label('الإمارة')->badge(),
                TextColumn::make('name_ar')->label('المنطقة')->searchable(),
                TextColumn::make('delivery_fee')->label('رسوم التوصيل')->money('AED'),
                TextColumn::make('min_order')->label('الحد الأدنى')->money('AED'),
            ])
            ->headerActions([
                AttachAction::make()->label('إضافة منطقة')->preloadRecordSelect()->multiple()
                    ->recordSelectOptionsQuery(fn ($q) => $q->with('emirate'))
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        TextInput::make('delivery_fee')->label('رسوم التوصيل')->numeric()->default(0)->required(),
                        TextInput::make('min_order')->label('الحد الأدنى للطلب')->numeric()->default(0)->required(),
                    ]),
            ])
            ->recordActions([EditAction::make(), DetachAction::make()->label('إزالة')]);
    }
}
