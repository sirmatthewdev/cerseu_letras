<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas que entran al panel.
 *
 * La contrasena no se muestra nunca —el campo llega vacio siempre— y al editar
 * solo se guarda si se escribe algo: sin esa condicion, abrir una ficha y
 * guardarla sin tocar nada dejaria la contrasena en blanco y a esa persona
 * fuera de su cuenta.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    // Obligatoria solo al crear: en edicion, vacia significa
                    // «dejala como estaba».
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Se deja vacía para no cambiarla.'
                        : 'Mínimo 8 caracteres.'),

                Select::make('role')
                    ->label('Rol')
                    ->required()
                    ->native(false)
                    ->default('user')
                    ->options([
                        'admin' => 'Administrador',
                        'user' => 'Usuario',
                    ])
                    ->helperText('Solo «Administrador» puede entrar al panel.'),

                Toggle::make('is_active')
                    ->label('Cuenta activa')
                    ->default(true),
            ]);
    }
}
