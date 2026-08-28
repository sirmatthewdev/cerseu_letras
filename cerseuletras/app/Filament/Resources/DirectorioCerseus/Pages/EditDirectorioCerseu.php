<?php

namespace App\Filament\Resources\DirectorioCerseus\Pages;

use App\Filament\Resources\DirectorioCerseus\DirectorioCerseuResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDirectorioCerseu extends EditRecord
{
    protected static string $resource = DirectorioCerseuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
