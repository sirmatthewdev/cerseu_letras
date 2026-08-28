<?php

namespace App\Filament\Resources\Testimonios\Pages;

use App\Filament\Resources\Testimonios\TestimonioResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTestimonio extends EditRecord
{
    protected static string $resource = TestimonioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
