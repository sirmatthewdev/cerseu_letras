<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Models\MenuItem;
use App\Support\DestinosPublicos;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                TextColumn::make('etiqueta')
                    ->searchable()
                    ->weight('semibold')
                    // Los hijos se sangran para que la jerarquia se vea sin
                    // tener que abrir cada uno.
                    ->formatStateUsing(fn (string $state, MenuItem $r): string => $r->parent_id ? "— {$state}" : $state),

                TextColumn::make('padre.etiqueta')
                    ->label('Cuelga de')
                    ->placeholder('Primer nivel')
                    ->toggleable(),

                TextColumn::make('destino')
                    ->label('A dónde lleva')
                    ->state(function (MenuItem $r): string {
                        if ($r->url) {
                            return $r->url;
                        }

                        if ($r->route_name) {
                            return DestinosPublicos::mapa()[$r->route_name] ?? "⚠ {$r->route_name}";
                        }

                        return 'Solo desplegable';
                    })
                    // Una ruta que ya no existe se marca: es exactamente como se
                    // rompe un menu sin que nada avise.
                    ->color(fn (string $state): string => str_starts_with($state, '⚠') ? 'danger' : 'gray')
                    ->limit(50),

                IconColumn::make('nueva_pestana')->label('Nueva pestaña')->boolean()->toggleable(),

                ToggleColumn::make('is_visible')->label('Visible'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
