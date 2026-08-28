<?php

namespace App\Filament\Resources\ContentPages\Tables;

use App\Models\ContentPage;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContentPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('slug')
                    ->label('Página')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ContentPage::PAGINAS[$state] ?? (string) $state),

                TextColumn::make('titulo')->label('Título')->placeholder('—')->wrap(),

                TextColumn::make('secciones_count')
                    ->label('Secciones')
                    ->counts('secciones')
                    ->alignCenter(),

                /*
                 * Marca las secciones que siguen con el texto de relleno. Hoy
                 * /tramites tiene tres que dicen literalmente «pendiente de
                 * carga», y desde una tabla que solo cuenta secciones eso no se
                 * distingue de una página terminada.
                 */
                TextColumn::make('pendientes')
                    ->label('Sin escribir')
                    ->badge()
                    ->state(fn (ContentPage $r): string => (string) $r->secciones()
                        ->where('cuerpo', 'like', '%pendiente de carga%')
                        ->count())
                    ->color(fn (string $state): string => $state === '0' ? 'success' : 'warning')
                    ->formatStateUsing(fn (string $state): string => $state === '0' ? 'Ninguna' : $state),
            ])
            ->recordActions([EditAction::make()]);
    }
}
