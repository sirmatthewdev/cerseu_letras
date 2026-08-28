<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use App\Models\MenuItem;
use App\Support\DestinosPublicos;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Menu de navegacion del sitio.
 *
 * Un elemento apunta a una ruta interna (`route_name`, estable frente a cambios
 * de URL) o a una direccion externa (`url`). Los que no tienen ninguna de las
 * dos son solo cabecera de desplegable — «Nosotros», «Formacion»— y eso es
 * valido, asi que ninguno de los dos campos es obligatorio.
 *
 * Las rutas internas se eligen de una lista y no se escriben: salen de
 * `DestinosPublicos`, que es la misma fuente que usa el sitio para resolverlas.
 * Escrito a mano, un nombre de ruta con una letra de mas deja la entrada muerta
 * y el menu no se queja.
 */
class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('etiqueta')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('parent_id')
                            ->label('Cuelga de')
                            ->placeholder('Primer nivel')
                            ->options(fn (): array => MenuItem::query()
                                ->whereNull('parent_id')
                                ->orderBy('orden')
                                ->pluck('etiqueta', 'id')
                                ->all())
                            // Un elemento no puede colgar de si mismo.
                            ->disableOptionWhen(fn ($value, $state, $record): bool => $record !== null && (int) $value === (int) $record->id),

                        TextInput::make('orden')
                            ->integer()
                            ->default(0)
                            ->helperText('Menor número, más a la izquierda.'),
                    ]),

                Section::make('A dónde lleva')
                    ->description('Una ruta interna o una dirección externa. Si se dejan las dos vacías, la entrada es solo cabecera de desplegable.')
                    ->columns(2)
                    ->schema([
                        Select::make('route_name')
                            ->label('Ruta interna')
                            ->placeholder('Ninguna')
                            ->searchable()
                            ->options(fn (): array => collect(DestinosPublicos::mapa())
                                ->map(fn (string $destino, string $ruta): string => "{$ruta}  ({$destino})")
                                ->all()),

                        TextInput::make('url')
                            ->label('Dirección externa')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://…'),

                        Toggle::make('nueva_pestana')
                            ->label('Abrir en una pestaña nueva'),

                        TextInput::make('icono')
                            ->maxLength(255)
                            ->placeholder('fas-graduation-cap'),
                    ]),

                Section::make('Visibilidad')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),

                        DatePicker::make('vigente_hasta')
                            ->label('Vigente hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('Para enlaces de convocatoria: pasada la fecha deja de mostrarse.'),
                    ]),
            ]);
    }
}
