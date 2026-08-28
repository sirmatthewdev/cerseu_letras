<?php

namespace App\Filament\Resources\Cronogramas;

use App\Filament\Resources\Cronogramas\Pages\CreateCronograma;
use App\Filament\Resources\Cronogramas\Pages\EditCronograma;
use App\Filament\Resources\Cronogramas\Pages\ListCronogramas;
use App\Filament\Resources\Cronogramas\Schemas\CronogramaForm;
use App\Filament\Resources\Cronogramas\Tables\CronogramasTable;
use App\Models\Cronograma;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CronogramaResource extends Resource
{
    protected static ?string $model = Cronograma::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static \UnitEnum|string|null $navigationGroup = 'Admisión';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Cronograma';

    protected static ?string $modelLabel = 'cronograma';

    protected static ?string $pluralModelLabel = 'cronogramas';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return CronogramaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CronogramasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCronogramas::route('/'),
            'create' => CreateCronograma::route('/create'),
            'edit' => EditCronograma::route('/{record}/edit'),
        ];
    }
}
