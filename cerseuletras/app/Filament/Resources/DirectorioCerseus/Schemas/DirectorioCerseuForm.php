<?php

namespace App\Filament\Resources\DirectorioCerseus\Schemas;

use App\Models\DirectorioCerseu;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Directorio del CERSEU.
 *
 * Hoy la tabla esta vacia: la Unidad no ha cargado su equipo todavia. El
 * formulario existe para que pueda hacerlo sin tocar codigo.
 */
class DirectorioCerseuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // La unidad se escribe, no se elige de una lista cerrada: el
                // CERSEU puede crear una nueva sin pedir un despliegue. Se
                // sugieren las ya usadas para que no se dupliquen por una tilde.
                TextInput::make('unidad_nombre')
                    ->label('Unidad')
                    ->required()
                    ->maxLength(255)
                    ->datalist(fn () => DirectorioCerseu::query()
                        ->distinct()
                        ->pluck('unidad_nombre')
                        ->all()),

                TextInput::make('cargo')->maxLength(255),

                TextInput::make('nombre_persona')
                    ->label('Nombre')
                    ->maxLength(255),

                TextInput::make('correo_persona')
                    ->label('Correo')
                    ->email()
                    ->maxLength(255),

                TextInput::make('anexo')->maxLength(50),

                TextInput::make('orden')
                    ->integer()
                    ->default(0)
                    ->helperText('Menor número, más arriba dentro de su unidad.'),

                Toggle::make('activo')
                    ->label('Visible en el sitio')
                    ->default(true),
            ]);
    }
}
