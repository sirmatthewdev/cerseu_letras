<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Resources\ContentPages\ContentPageResource;
use Filament\Resources\Pages\ListRecords;

class ListContentPages extends ListRecords
{
    protected static string $resource = ContentPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
