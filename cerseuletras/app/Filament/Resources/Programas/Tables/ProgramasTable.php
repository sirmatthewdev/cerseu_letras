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
                    ->url(fn (Programa $r): string => route('admin.vista-previa.programa', $r)),

                Action::make('ver')
                    ->label('Ver en el sitio')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (Programa $r): bool => $r->estado !== Programa::ESTADO_BORRADOR)
                    ->url(fn (Programa $r): ?string => $r->url)
                    ->openUrlInNewTab(),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
