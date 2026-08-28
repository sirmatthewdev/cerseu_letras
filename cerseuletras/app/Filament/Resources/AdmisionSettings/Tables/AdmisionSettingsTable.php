<?php

namespace App\Filament\Resources\AdmisionSettings\Tables;

use App\Models\AdmisionSetting;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdmisionSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Tres filas, una por tipo: paginar sería poner un pie de tabla que
            // nunca hace nada.
            ->paginated(false)
            ->columns([
                TextColumn::make('tipo')
                    ->label('Tipo de oferta')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->plural() ?? '—'),

                TextColumn::make('hero_titulo')->label('Titular')->placeholder('—')->wrap(),

                /*
                 * Lo que hace falta saber de un vistazo: cuáles siguen en blanco.
                 * Los tres lo están hoy, porque traían el proceso de la Unidad de
                 * Posgrado y se vació sin esperar reemplazo. Esta columna dice
                 * cuánto queda por escribir, en vez de dejar tres filas que
                 * parecen llenas porque tienen un título.
                 */
                TextColumn::make('estado')
                    ->label('Contenido')
                    ->badge()
                    ->state(function (AdmisionSetting $r): string {
                        $pasos = count((array) $r->pasos);
                        $requisitos = count((array) $r->requisitos_lista);

                        if ($pasos === 0 && $requisitos === 0) {
                            return 'En blanco';
                        }

                        return "{$pasos} pasos · {$requisitos} requisitos";
                    })
                    ->color(fn (string $state): string => $state === 'En blanco' ? 'warning' : 'success'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
