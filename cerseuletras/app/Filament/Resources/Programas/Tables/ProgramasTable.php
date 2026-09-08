<?php

namespace App\Filament\Resources\Programas\Tables;

use App\Models\Programa;
use App\Models\TipoOferta;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Listado de programas.
 *
 * La columna que más importa es el estado: con 39 fichas, lo que se viene a
 * mirar es qué está publicado y qué no.
 */
class ProgramasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('semibold')
                    ->description(fn (Programa $r): ?string => $r->mencion),

                TextColumn::make('grado')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                TextColumn::make('modalidad')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('horas_academicas')
                    ->label('Horas')
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('estado')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Programa::ESTADO_PUBLICADO => 'Publicado',
                        Programa::ESTADO_PROXIMAMENTE => 'Próximamente',
                        Programa::ESTADO_BORRADOR => 'Borrador',
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Programa::ESTADO_PUBLICADO => 'success',
                        Programa::ESTADO_PROXIMAMENTE => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('docentes_count')
                    ->label('Docentes')
                    ->counts('docentes')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options([
                        Programa::ESTADO_PUBLICADO => 'Publicado',
                        Programa::ESTADO_PROXIMAMENTE => 'Próximamente',
                        Programa::ESTADO_BORRADOR => 'Borrador',
                    ]),

                SelectFilter::make('grado')
                    ->label('Tipo de oferta')
                    ->options(array_combine(TipoOferta::grados(), TipoOferta::grados())),
            ])
            ->recordActions([
                /*
                 * Un borrador no está en el sitio, así que «ver» llevaría a un
                 * 404: para esos la acción es la vista previa, que lo genera
                 * aparte sin publicarlo. Es la misma distinción que se hizo en
                 * el panel anterior.
                 */
                Action::make('vista_previa')
                    ->label('Vista previa')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (Programa $r): bool => $r->estado === Programa::ESTADO_BORRADOR)
                    ->url(fn (Programa $r): string => route('gestion.vista-previa.programa', $r)),

                Action::make('ver')
                    ->label('Ver en el sitio')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (Programa $r): bool => $r->estado !== Programa::ESTADO_BORRADOR)
                    ->url(fn (Programa $r): ?string => $r->url)
                    ->openUrlInNewTab(),

                /*
                 * Publicar y despublicar de un clic, como en el panel anterior.
                 * `estado` se puede cambiar entrando a la ficha, pero con 39
                 * programas esto es lo que se hace a diario: obligar a abrir el
                 * formulario entero para mover un interruptor es la clase de
                 * roce que acaba con fichas sin publicar.
                 */
                Action::make('publicar')
                    ->label(fn (Programa $r): string => $r->estado === Programa::ESTADO_PUBLICADO
                        ? 'Pasar a borrador'
                        : 'Publicar')
                    ->icon(fn (Programa $r): string => $r->estado === Programa::ESTADO_PUBLICADO
                        ? 'heroicon-o-arrow-uturn-left'
                        : 'heroicon-o-check-circle')
                    ->color(fn (Programa $r): string => $r->estado === Programa::ESTADO_PUBLICADO
                        ? 'gray'
                        : 'success')
                    ->requiresConfirmation()
                    ->action(function (Programa $r): void {
                        $r->update([
                            'estado' => $r->estado === Programa::ESTADO_PUBLICADO
                                ? Programa::ESTADO_BORRADOR
                                : Programa::ESTADO_PUBLICADO,
                        ]);
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
