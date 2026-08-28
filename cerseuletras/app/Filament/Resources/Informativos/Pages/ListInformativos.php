<?php

namespace App\Filament\Resources\Informativos\Pages;

use App\Filament\Resources\Informativos\InformativoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInformativos extends ListRecords
{
    protected static string $resource = InformativoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
