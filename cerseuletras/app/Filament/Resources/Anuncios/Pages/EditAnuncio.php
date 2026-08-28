<?php

namespace App\Filament\Resources\Anuncios\Pages;

use App\Filament\Resources\Anuncios\AnuncioResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

use App\Filament\Resources\Anuncios\Concerns\MideLaImagen;

class EditAnuncio extends EditRecord
{
    use MideLaImagen;

    protected static string $resource = AnuncioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /** @param array<string, mixed> $datos */
    protected function mutateFormDataBeforeSave(array $datos): array
    {
        return $this->conMedidas($datos);
    }
}
