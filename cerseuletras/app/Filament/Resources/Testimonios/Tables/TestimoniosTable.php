<?php

namespace App\Filament\Resources\Testimonios\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TestimoniosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->circular(),

                TextColumn::make('nombre')->searchable()->sortable()->weight('semibold'),

                TextColumn::make('programa.nombre')
                    ->label('Programa')
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('contenido')
                    ->limit(70)
                    ->wrap()
                    ->toggleable(),

                ToggleColumn::make('estado')->label('Visible'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
