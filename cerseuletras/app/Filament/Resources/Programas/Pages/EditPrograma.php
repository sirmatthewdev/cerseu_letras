<?php

namespace App\Filament\Resources\Programas\Pages;

use App\Filament\Resources\Programas\ProgramaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPrograma extends EditRecord
{
    protected static string $resource = ProgramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
