<?php

namespace App\Filament\Resources\Informativos\Pages;

use App\Filament\Resources\Informativos\InformativoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditInformativo extends EditRecord
{
    protected static string $resource = InformativoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
