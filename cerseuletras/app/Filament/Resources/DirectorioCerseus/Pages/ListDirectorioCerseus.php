<?php

namespace App\Filament\Resources\DirectorioCerseus\Pages;

use App\Filament\Resources\DirectorioCerseus\DirectorioCerseuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDirectorioCerseus extends ListRecords
{
    protected static string $resource = DirectorioCerseuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
