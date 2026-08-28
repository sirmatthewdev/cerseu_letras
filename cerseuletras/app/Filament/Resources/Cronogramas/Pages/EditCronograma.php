<?php

namespace App\Filament\Resources\Cronogramas\Pages;

use App\Filament\Resources\Cronogramas\CronogramaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCronograma extends EditRecord
{
    protected static string $resource = CronogramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
