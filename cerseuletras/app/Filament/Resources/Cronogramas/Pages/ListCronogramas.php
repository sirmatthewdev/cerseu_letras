<?php

namespace App\Filament\Resources\Cronogramas\Pages;

use App\Filament\Resources\Cronogramas\CronogramaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCronogramas extends ListRecords
{
    protected static string $resource = CronogramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
