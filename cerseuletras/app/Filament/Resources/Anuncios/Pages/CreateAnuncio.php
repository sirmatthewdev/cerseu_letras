<?php

namespace App\Filament\Resources\Anuncios\Pages;

use App\Filament\Resources\Anuncios\AnuncioResource;
use Filament\Resources\Pages\CreateRecord;

use App\Filament\Resources\Anuncios\Concerns\MideLaImagen;

class CreateAnuncio extends CreateRecord
{
    use MideLaImagen;

    protected static string $resource = AnuncioResource::class;

    /** @param array<string, mixed> $datos */
    protected function mutateFormDataBeforeCreate(array $datos): array
    {
        return $this->conMedidas($datos);
    }
}
