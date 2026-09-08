<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
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

                /*
                 * Rol y actividad se bloquean en dos casos: sobre la propia
                 * cuenta y sobre la del ultimo administrador activo. En
                 * cualquiera de los dos, guardar el cambio dejaria el panel sin
                 * nadie que pueda entrar —y sin nadie dentro, tampoco hay quien
                 * lo deshaga—.
                 *
                 * `disabled()` no es solo cosmetico en Filament: un campo
                 * deshabilitado no se deshidrata, asi que el valor no viaja al
                 * guardar aunque alguien lo fuerce desde el navegador.
                 */
                Select::make('role')
                    ->label('Rol')
                    ->required()
                    ->native(false)
                    ->default('user')
                    ->options([
                        'admin' => 'Administrador',
                        'user' => 'Usuario',
                    ])
                    ->disabled(fn (?User $record): bool => self::esIntocable($record))
                    ->helperText(fn (?User $record): string => self::esIntocable($record)
                        ? self::MOTIVO
                        : 'Solo «Administrador» puede entrar al panel.'),

                Toggle::make('is_active')
                    ->label('Cuenta activa')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => self::esIntocable($record))
                    ->helperText(fn (?User $record): ?string => self::esIntocable($record) ? self::MOTIVO : null),
            ]);
    }

    private const MOTIVO = 'No se puede cambiar: dejaría el sitio sin ningún administrador activo.';

    /** La propia cuenta y la del último administrador activo no se degradan. */
    public static function esIntocable(?User $record): bool
    {
        if (! $record?->exists) {
            return false;
        }

        return $record->id === auth()->id() || $record->esUltimoAdminActivo();
    }
}
