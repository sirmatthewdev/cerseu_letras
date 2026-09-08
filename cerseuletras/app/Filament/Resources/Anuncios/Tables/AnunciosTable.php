<?php

namespace App\Filament\Resources\Anuncios\Tables;

use App\Models\Anuncio;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AnunciosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                ImageColumn::make('imagen')->label('')->disk('public'),

                TextColumn::make('titulo')->label('Título')->searchable()->wrap()->weight('semibold'),

                /*
                 * Cuanto se va a recortar la imagen en la portada.
                 *
                 * El marco es 4:5 y se rellena con `cover`, asi que una imagen
                 * con otra proporcion pierde bordes — a veces la mitad de un
                 * texto. El modelo ya sabia calcularlo y decir por donde corta;
                 * sin esta columna ese calculo no se enseñaba en ninguna parte
                 * y el recorte solo se descubria mirando la portada.
                 */
                TextColumn::make('recorte')
                    ->label('Recorte')
                    ->state(fn (Anuncio $record): string => $record->recorte_notable
                        ? "Se recorta {$record->recorte_porcentaje}% {$record->recorte_lado}"
                        : 'Cuadra')
                    ->badge()
                    ->color(fn (Anuncio $record): string => $record->recorte_notable ? 'warning' : 'success')
                    ->placeholder('—'),

                TextColumn::make('visible_desde')
                    ->label('Vigencia')
                    ->date('d/m/Y')
                    ->description(fn ($record): ?string => $record->visible_hasta
                        ? 'hasta ' . $record->visible_hasta->format('d/m/Y')
                        : null)
                    ->placeholder('Siempre'),

                // Un anuncio activo pero fuera de fechas no sale, y desde la
                // tabla eso no se ve: esta columna lo dice.
                TextColumn::make('estado_real')
                    ->label('Ahora')
                    ->badge()
                    ->state(function ($record): string {
                        if (! $record->is_visible) {
                            return 'Desactivado';
                        }

                        $hoy = now()->startOfDay();

                        if ($record->visible_desde && $hoy->lt($record->visible_desde)) {
                            return 'Aún no';
                        }

                        if ($record->visible_hasta && $hoy->gt($record->visible_hasta)) {
                            return 'Caducado';
                        }

                        return 'Se muestra';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Se muestra' => 'success',
                        'Aún no' => 'warning',
                        default => 'gray',
                    }),

                ToggleColumn::make('is_visible')->label('Activo'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
