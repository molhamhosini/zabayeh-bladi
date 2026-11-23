<?php

namespace App\Filament\Resources\DeliverySlots\Pages;

use App\Filament\Resources\DeliverySlots\DeliverySlotResource;
use App\Models\Branch;
use App\Models\DeliverySlot;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDeliverySlots extends ListRecords
{
    protected static string $resource = DeliverySlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('تحديث المواعيد من أوقات التوصيل')
                ->icon('heroicon-o-sparkles')
                ->modalDescription('ينشئ المواعيد لكل إمارة يوصل لها الفرع حسب "أوقات التوصيل" الخاصة بها. يتم هذا تلقائياً أيضاً كل يوم وعند تعديل الأوقات.')
                ->schema([
                    Select::make('branch_id')->label('الفرع')->options(fn () => Branch::pluck('name_ar', 'id'))->required()
                        ->hidden(fn () => DeliverySlotResource::inBranchPanel()),
                    TextInput::make('days')->label('عدد الأيام القادمة')->numeric()->minValue(1)->maxValue(60)->default(14)->required(),
                ])
                ->action(function (array $data) {
                    $data = DeliverySlotResource::stampBranch($data);
                    $n = DeliverySlot::generateFor((int) $data['branch_id'], (int) $data['days']);
                    Notification::make()->title("تم إنشاء {$n} موعد")->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
