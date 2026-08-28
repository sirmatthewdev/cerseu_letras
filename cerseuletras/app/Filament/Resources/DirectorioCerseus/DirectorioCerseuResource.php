<?php

namespace App\Filament\Resources\DirectorioCerseus;

use App\Filament\Resources\DirectorioCerseus\Pages\CreateDirectorioCerseu;
use App\Filament\Resources\DirectorioCerseus\Pages\EditDirectorioCerseu;
use App\Filament\Resources\DirectorioCerseus\Pages\ListDirectorioCerseus;
use App\Filament\Resources\DirectorioCerseus\Schemas\DirectorioCerseuForm;
use App\Filament\Resources\DirectorioCerseus\Tables\DirectorioCerseusTable;
use App\Models\DirectorioCerseu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DirectorioCerseuResource extends Resource
{
    protected static ?string $model = DirectorioCerseu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static \UnitEnum|string|null $navigationGroup = 'Institución';

    protected static ?int $navigationSort = 80;

    protected static ?string $navigationLabel = 'Directorio';

    protected static ?string $modelLabel = 'entrada del directorio';

    protected static ?string $pluralModelLabel = 'entradas del directorio';

    protected static ?string $recordTitleAttribute = 'nombre_persona';

    public static function form(Schema $schema): Schema
    {
        return DirectorioCerseuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DirectorioCerseusTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDirectorioCerseus::route('/'),
            'create' => CreateDirectorioCerseu::route('/create'),
            'edit' => EditDirectorioCerseu::route('/{record}/edit'),
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
