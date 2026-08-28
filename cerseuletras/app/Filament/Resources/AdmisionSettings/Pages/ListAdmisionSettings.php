<?php

namespace App\Filament\Resources\AdmisionSettings\Pages;

use App\Filament\Resources\AdmisionSettings\AdmisionSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListAdmisionSettings extends ListRecords
{
    protected static string $resource = AdmisionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
