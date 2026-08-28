<?php

namespace App\Filament\Resources\Informativos;

use App\Filament\Resources\Informativos\Pages\CreateInformativo;
use App\Filament\Resources\Informativos\Pages\EditInformativo;
use App\Filament\Resources\Informativos\Pages\ListInformativos;
use App\Filament\Resources\Informativos\Schemas\InformativoForm;
use App\Filament\Resources\Informativos\Tables\InformativosTable;
use App\Models\Informativo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InformativoResource extends Resource
{
    protected static ?string $model = Informativo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static \UnitEnum|string|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 70;

    protected static ?string $navigationLabel = 'Documentos y recursos';

    protected static ?string $modelLabel = 'recurso informativo';

    protected static ?string $pluralModelLabel = 'recursos informativos';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function form(Schema $schema): Schema
    {
        return InformativoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InformativosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInformativos::route('/'),
            'create' => CreateInformativo::route('/create'),
            'edit' => EditInformativo::route('/{record}/edit'),
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
