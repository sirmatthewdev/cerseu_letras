<?php

namespace App\Filament\Resources\AdmisionSettings;

use App\Filament\Resources\AdmisionSettings\Pages\CreateAdmisionSetting;
use App\Filament\Resources\AdmisionSettings\Pages\EditAdmisionSetting;
use App\Filament\Resources\AdmisionSettings\Pages\ListAdmisionSettings;
use App\Filament\Resources\AdmisionSettings\Schemas\AdmisionSettingForm;
use App\Filament\Resources\AdmisionSettings\Tables\AdmisionSettingsTable;
use App\Models\AdmisionSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdmisionSettingResource extends Resource
{
    protected static ?string $model = AdmisionSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Admisión';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Proceso de admisión';

    protected static ?string $modelLabel = 'proceso de admisión';

    protected static ?string $pluralModelLabel = 'procesos de admisión';

    protected static ?string $recordTitleAttribute = 'tipo';

    public static function form(Schema $schema): Schema
    {
        return AdmisionSettingForm::configure($schema);
    }

    /**
     * Hay una fila por tipo (o por pagina) y las siembra el seeder desde el
     * enum. Crear una a mano produciria una fila que el sitio no consulta, y
     * borrarla dejaria esa pagina sin contenido y sin forma de recuperarlo
     * desde el panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return AdmisionSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmisionSettings::route('/'),
            'edit' => EditAdmisionSetting::route('/{record}/edit'),
        ];
    }
}
