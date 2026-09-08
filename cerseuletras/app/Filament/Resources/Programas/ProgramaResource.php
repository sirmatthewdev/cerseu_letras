<?php

namespace App\Filament\Resources\Programas;

use App\Filament\Resources\Programas\Pages\CreatePrograma;
use App\Filament\Resources\Programas\Pages\EditPrograma;
use App\Filament\Resources\Programas\Pages\ListProgramas;
use App\Filament\Resources\Programas\Schemas\ProgramaForm;
use App\Filament\Resources\Programas\Tables\ProgramasTable;
use App\Filament\Resources\Programas\RelationManagers\DocentesRelationManager;
use App\Models\Programa;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Oferta académica: talleres, cursos y especializaciones.
 *
 * El recurso mas usado del panel, y el que sustituye al controlador mas largo
 * (416 lineas). Se apoya en `TipoOferta` para los tipos y en los estados del
 * modelo, en vez de repetir las listas aqui.
 */
class ProgramaResource extends Resource
{
    protected static ?string $model = Programa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static \UnitEnum|string|null $navigationGroup = 'Oferta';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Oferta académica';

    protected static ?string $modelLabel = 'programa';

    protected static ?string $pluralModelLabel = 'programas';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return ProgramaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgramasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocentesRelationManager::class,
        ];
    }

    /** Cuantos hay publicados, en la barra de navegacion. */
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('estado', Programa::ESTADO_PUBLICADO)->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramas::route('/'),
            'create' => CreatePrograma::route('/create'),
            'edit' => EditPrograma::route('/{record}/edit'),
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
