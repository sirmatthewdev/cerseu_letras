<?php

namespace App\Filament\Resources\Docentes;

use App\Filament\Resources\Docentes\Pages\CreateDocente;
use App\Filament\Resources\Docentes\Pages\EditDocente;
use App\Filament\Resources\Docentes\Pages\ListDocentes;
use App\Filament\Resources\Docentes\Schemas\DocenteForm;
use App\Filament\Resources\Docentes\Tables\DocentesTable;
use App\Models\Docente;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Plana docente.
 *
 * Primer recurso de la migracion del panel a Filament. Sirve de patron para los
 * demas: etiquetas en castellano, icono y grupo de navegacion propios, y el
 * borrado suave del modelo respetado tal cual — `Docente` usa `SoftDeletes`, y
 * la papelera del panel de siempre depende de que siga siendo asi.
 */
class DocenteResource extends Resource
{
    protected static ?string $model = Docente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Plana docente';

    protected static ?string $modelLabel = 'docente';

    protected static ?string $pluralModelLabel = 'docentes';

    protected static ?string $recordTitleAttribute = 'apellidos';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return DocenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocentesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /** Cuantos hay, en la barra de navegacion. */
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocentes::route('/'),
            'create' => CreateDocente::route('/create'),
            'edit' => EditDocente::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
