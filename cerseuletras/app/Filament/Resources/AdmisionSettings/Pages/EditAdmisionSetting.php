<?php

namespace App\Filament\Resources\AdmisionSettings\Pages;

use App\Filament\Resources\AdmisionSettings\AdmisionSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdmisionSetting extends EditRecord
{
    protected static string $resource = AdmisionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
