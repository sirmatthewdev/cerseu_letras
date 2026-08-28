<?php

namespace App\Filament\Resources\Anuncios;

use App\Filament\Resources\Anuncios\Pages\CreateAnuncio;
use App\Filament\Resources\Anuncios\Pages\EditAnuncio;
use App\Filament\Resources\Anuncios\Pages\ListAnuncios;
use App\Filament\Resources\Anuncios\Schemas\AnuncioForm;
use App\Filament\Resources\Anuncios\Tables\AnunciosTable;
use App\Models\Anuncio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AnuncioResource extends Resource
{
    protected static ?string $model = Anuncio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static \UnitEnum|string|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'Anuncios';

    protected static ?string $modelLabel = 'anuncio';

    protected static ?string $pluralModelLabel = 'anuncios';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function form(Schema $schema): Schema
    {
        return AnuncioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnunciosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnuncios::route('/'),
            'create' => CreateAnuncio::route('/create'),
            'edit' => EditAnuncio::route('/{record}/edit'),
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
