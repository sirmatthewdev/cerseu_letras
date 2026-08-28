<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    /**
     * Sin botón de crear.
     *
     * `LeadResource::canCreate()` ya devuelve false, pero el `CreateAction` que
     * pone el generador se pintaba igual: el botón salía y llevaba a una ruta
     * que no existe. Se retira aquí, que es donde estaba.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
