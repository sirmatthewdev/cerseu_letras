<?php

namespace App\Filament\Resources\DirectorioCerseus\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class DirectorioCerseusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            // Agrupado por unidad, que es como lo presenta el sitio.
            ->groups(['unidad_nombre'])
            ->defaultGroup('unidad_nombre')
            ->columns([
                TextColumn::make('nombre_persona')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('semibold')
                    ->placeholder('—'),

                TextColumn::make('cargo')->searchable()->wrap()->placeholder('—'),

                TextColumn::make('correo_persona')
                    ->label('Correo')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('anexo')->placeholder('—')->alignCenter(),

                ToggleColumn::make('activo')->label('Visible'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
