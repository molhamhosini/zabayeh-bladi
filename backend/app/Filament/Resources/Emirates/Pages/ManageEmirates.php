<?php

namespace App\Filament\Resources\Emirates\Pages;

use App\Filament\Resources\Emirates\EmirateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmirates extends ManageRecords
{
    protected static string $resource = EmirateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
