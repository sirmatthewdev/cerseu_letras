<?php

namespace App\Filament\Resources\Docentes\Pages;

use App\Filament\Resources\Docentes\DocenteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDocente extends EditRecord
{
    protected static string $resource = DocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
