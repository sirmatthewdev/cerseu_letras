<?php

namespace App\Filament\Resources\Docentes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Listado de docentes.
 *
 * Ordena por apellidos, que es como se busca a una persona en una lista de
 * veinte. El generador ordenaba por fecha de creación, que no le dice nada a
 * nadie.
 */
class DocentesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('apellidos')
            ->columns([
                ImageColumn::make('foto')
                    ->label('')
                    ->disk('public')
                    ->circular(),
                    // Sin imagen por defecto a proposito. `Docente::foto_url`
                    // apunta a `images/profesor-default.jpg`, que NO existe en
                    // el repositorio; el sitio no lo nota porque detecta ese
                    // nombre y pinta las iniciales en su lugar. Poner aqui esa
                    // ruta habria sido una segunda referencia rota, esta vez
                    // sin nada que la tapara.

                TextColumn::make('apellidos')
                    ->label('Apellidos')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('nombres')
                    ->label('Nombres')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grado')
                    ->label('Grado')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('programas_count')
                    ->label('Programas')
                    // Cuenta en la consulta y no cargando la relación: con 20
                    // docentes serían 20 consultas más por cada carga de tabla.
                    ->counts('programas')
                    ->alignCenter()
                    ->sortable(),

                ToggleColumn::make('estado')
                    ->label('Visible'),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('estado')
                    ->label('Visibilidad')
                    ->placeholder('Todos')
                    ->trueLabel('Solo visibles')
                    ->falseLabel('Solo ocultos'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
