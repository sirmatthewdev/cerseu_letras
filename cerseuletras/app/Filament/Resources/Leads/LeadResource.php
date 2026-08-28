<?php

namespace App\Filament\Resources\Leads;

use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Solicitudes recibidas por el formulario del sitio.
 *
 * El único recurso de solo lectura del panel, y a propósito: estos registros
 * los crea el visitante. Editarlos a mano sería falsear lo que alguien pidió, y
 * crearlos, inventar una solicitud que nadie hizo. De ahí que no tenga
 * formulario ni páginas de crear y editar — no es que estén sin migrar.
 *
 * Lo que sí conserva del panel anterior son las dos acciones que importan:
 * exportar a CSV y reenviar el aviso cuando el correo falló.
 */
class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static \UnitEnum|string|null $navigationGroup = 'Institución';

    protected static ?int $navigationSort = 85;

    protected static ?string $navigationLabel = 'Solicitudes';

    protected static ?string $modelLabel = 'solicitud';

    protected static ?string $pluralModelLabel = 'solicitudes';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
        ];
    }

    /**
     * Cuántas quedaron sin avisar.
     *
     * Es lo único urgente de esta pantalla: una solicitud cuyo aviso no salió
     * se queda enterrada en la tabla sin que nadie se entere. Cuando no hay
     * ninguna, la insignia no aparece — un cero permanente deja de mirarse.
     */
    public static function getNavigationBadge(): ?string
    {
        $fallidas = static::getModel()::query()
            ->whereNull('aviso_enviado_en')
            ->whereNotNull('aviso_error')
            ->count();

        return $fallidas > 0 ? (string) $fallidas : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
