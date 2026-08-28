<?php

namespace App\Filament\Resources\Informativos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InformativosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->groups(['categoria'])
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->wrap()
                    ->weight('semibold'),

                TextColumn::make('categoria')->label('Categoría')->badge()->placeholder('—'),

                TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => (int) $state === 1 ? 'Enlace' : 'PDF')
                    ->color(fn ($state): string => (int) $state === 1 ? 'info' : 'gray'),

                TextColumn::make('url')->limit(45)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipo')->options([0 => 'PDF', 1 => 'Enlace']),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
