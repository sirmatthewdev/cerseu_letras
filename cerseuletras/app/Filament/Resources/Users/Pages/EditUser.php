<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // La tabla ya protegia este borrado; esta pantalla no, y era la
            // misma accion: desde aqui si se podia borrar uno su propia cuenta.
            DeleteAction::make()
                ->visible(fn (User $record): bool => ! UserForm::esIntocable($record)),
        ];
    }
}
