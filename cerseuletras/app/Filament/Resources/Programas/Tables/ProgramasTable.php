<?php

namespace App\Filament\Resources\Programas\Tables;

use App\Models\Programa;
use App\Models\TipoOferta;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Table;

/**
 * Listado de programas.
 *
 * La columna que más importa es «Visible»: con decenas de fichas, lo que se
 * viene a mirar —y a cambiar, cada temporada— es qué sale en el sitio y qué
 * no. Es un interruptor en la propia fila, como en la plana docente.
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

                ToggleColumn::make('estado')
                    ->label('Visible')
                    ->sortable()
                    ->getStateUsing(fn (Programa $r): bool => $r->estado === Programa::ESTADO_PUBLICADO)
                    ->updateStateUsing(fn (Programa $r, ?bool $state) => $r->update([
                        'estado' => $state ? Programa::ESTADO_PUBLICADO : Programa::ESTADO_BORRADOR,
                    ])),

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
                TernaryFilter::make('estado')
                    ->label('Visible')
                    ->trueLabel('Solo visibles')
                    ->falseLabel('Solo no visibles')
                    ->queries(
                        true: fn (Builder $q) => $q->where('estado', Programa::ESTADO_PUBLICADO),
                        false: fn (Builder $q) => $q->where('estado', '!=', Programa::ESTADO_PUBLICADO),
                    ),

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

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Al cambiar de temporada se encienden o apagan varios
                    // cursos a la vez: uno a uno serían decenas de clics.
                    BulkAction::make('hacer_visibles')
                        ->label('Hacer visibles')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $programas) => $programas->each->update(['estado' => Programa::ESTADO_PUBLICADO]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('ocultar')
                        ->label('Ocultar del sitio')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $programas) => $programas->each->update(['estado' => Programa::ESTADO_BORRADOR]))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
