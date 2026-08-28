<?php

namespace App\Filament\Resources\Eventos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EventosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Por `orden` y luego por fecha: es como los coloca el sitio, asi que
            // la tabla ensena el mismo orden que vera el visitante.
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                ImageColumn::make('imagen')->label('')->disk('public'),

                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->wrap()
                    ->weight('semibold'),

                TextColumn::make('fecha_inicio')
                    ->label('Fechas')
                    ->date('d/m/Y')
                    ->description(fn ($record): ?string => $record->fecha_fin
                        ? 'hasta ' . $record->fecha_fin->format('d/m/Y')
                        : null)
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('tipo_url')->label('Enlace')->badge()->placeholder('—'),

                ToggleColumn::make('activo')->label('Visible'),
            ])
            ->filters([
                TernaryFilter::make('activo')
                    ->label('Visibilidad')
                    ->placeholder('Todos')
                    ->trueLabel('Solo visibles')
                    ->falseLabel('Solo ocultos'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
