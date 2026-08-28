<?php

namespace App\Filament\Resources\Cronogramas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CronogramasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('effective_date', 'desc')
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->weight('semibold')->wrap(),

                TextColumn::make('code')->label('Código')->badge()->placeholder('—'),

                TextColumn::make('effective_date')
                    ->label('Vigente desde')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('items_count')->label('Filas')->counts('items')->alignCenter(),

                ToggleColumn::make('is_active')->label('Activo'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
