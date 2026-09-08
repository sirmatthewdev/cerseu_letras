<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Models\Lead;
use App\Models\TipoOferta;
use App\Services\AvisoDeSolicitud;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Solicitudes recibidas por el formulario del sitio.
 *
 * Se leen, no se escriben: las crea el visitante, y editarlas a mano seria
 * falsear lo que alguien pidio. De ahi que no haya formulario ni boton de crear.
 *
 * Lo que si hace falta —y venia del panel anterior— son dos cosas: exportar a
 * CSV, porque la Unidad trabaja las solicitudes en hoja de calculo, y reenviar
 * el aviso cuando el correo fallo. Sin lo segundo, una solicitud cuyo aviso no
 * salio se queda enterrada en la tabla sin que nadie se entere.
 */
class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Recibida')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('nombres')
                    ->label('Quién')
                    ->formatStateUsing(fn ($state, Lead $r): string => trim("{$r->nombres} {$r->apellidos}"))
                    ->description(fn (Lead $r): ?string => $r->correo)
                    ->searchable(['nombres', 'apellidos', 'correo'])
                    ->weight('semibold'),

                TextColumn::make('telefono')->label('Teléfono')->copyable()->placeholder('—'),

                /*
                 * El estado llega ya como enum: el modelo castea `tipo` a
                 * TipoOferta. Se forzaba a cadena —`(string) $state`— y eso
                 * revienta con «Object of class TipoOferta could not be
                 * converted to string», asi que el listado entero caia en
                 * cuanto una solicitud tenia tipo. Con la tabla vacia no se
                 * notaba: la columna no llega a pintarse.
                 */
                TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof TipoOferta
                        ? $state->singular()
                        : (TipoOferta::desdeSlug((string) $state)?->singular() ?? (string) $state))
                    ->placeholder('—'),

                TextColumn::make('programa.nombre')
                    ->label('Programa')
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('region')
                    ->label('Procedencia')
                    ->formatStateUsing(fn ($state, Lead $r): string => trim(implode(', ', array_filter([$r->region, $r->pais]))) ?: '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Lo primero que hay que poder ver de un vistazo: si el CERSEU
                // se entero de esta solicitud o si el correo se quedo por el
                // camino.
                TextColumn::make('aviso')
                    ->label('Aviso')
                    ->badge()
                    ->state(fn (Lead $r): string => $r->avisoPendiente()
                        ? 'Falló'
                        : ($r->aviso_enviado_en ? 'Enviado' : 'Sin aviso'))
                    ->color(fn (string $state): string => match ($state) {
                        'Enviado' => 'success',
                        'Falló' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (Lead $r): ?string => $r->aviso_error),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo de oferta')
                    ->options(fn (): array => collect(TipoOferta::cases())
                        ->mapWithKeys(fn (TipoOferta $t) => [$t->slug() => $t->plural()])
                        ->all()),

                TernaryFilter::make('aviso_pendiente')
                    ->label('Aviso')
                    ->placeholder('Todas')
                    ->trueLabel('Solo las que fallaron')
                    ->falseLabel('Solo las avisadas')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('aviso_enviado_en')->whereNotNull('aviso_error'),
                        false: fn (Builder $q) => $q->whereNotNull('aviso_enviado_en'),
                    ),
            ])
            ->headerActions([
                // Se reutiliza la exportacion que ya existe en vez de rehacerla:
                // el CSV lleva el separador y la codificacion que abre Excel en
                // castellano sin pelearse, y eso costo afinarlo.
                Action::make('exportar')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (): string => route('gestion.solicitudes.exportar'))
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                Action::make('reenviar')
                    ->label('Reenviar aviso')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Lead $r): bool => $r->avisoPendiente())
                    ->action(function (Lead $r): void {
                        if (AvisoDeSolicitud::enviar($r)) {
                            Notification::make()->title('Aviso reenviado.')->success()->send();

                            return;
                        }

                        // El motivo tal cual: quien administra necesita saber si
                        // falta la contraseña, si el servidor rechaza o si no hay
                        // destinatario configurado.
                        Notification::make()
                            ->title('No se pudo enviar')
                            ->body($r->aviso_error)
                            ->danger()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
