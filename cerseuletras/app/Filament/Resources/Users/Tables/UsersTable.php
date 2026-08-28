<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->weight('semibold'),

                TextColumn::make('email')->label('Correo')->searchable()->copyable(),

                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'admin' ? 'Administrador' : 'Usuario')
                    ->color(fn (?string $state): string => $state === 'admin' ? 'success' : 'gray'),

                ToggleColumn::make('is_active')->label('Activa'),

                TextColumn::make('created_at')
                    ->label('Alta')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rol')
                    ->options(['admin' => 'Administrador', 'user' => 'Usuario']),
            ])
            ->recordActions([
                EditAction::make(),
                // Nadie puede borrarse a si mismo: hacerlo cierra la sesion en
                // curso y, si es la unica cuenta de administrador, deja el panel
                // sin nadie que pueda entrar.
                DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->id !== auth()->id()),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
