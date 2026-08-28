<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Models\Document;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->wrap()->weight('semibold'),
                TextColumn::make('type')->label('Tipo')->badge()->sortable(),
                TextColumn::make('original_name')->label('Archivo')->limit(40)->placeholder('—')->toggleable(),
                ToggleColumn::make('published')->label('Publicado'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(fn () => Document::query()->distinct()->pluck('type', 'type')->all()),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
