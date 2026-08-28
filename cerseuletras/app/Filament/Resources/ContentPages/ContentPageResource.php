<?php

namespace App\Filament\Resources\ContentPages;

use App\Filament\Resources\ContentPages\Pages\CreateContentPage;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use App\Filament\Resources\ContentPages\Pages\ListContentPages;
use App\Filament\Resources\ContentPages\Schemas\ContentPageForm;
use App\Filament\Resources\ContentPages\Tables\ContentPagesTable;
use App\Models\ContentPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContentPageResource extends Resource
{
    protected static ?string $model = ContentPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static \UnitEnum|string|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Páginas';

    protected static ?string $modelLabel = 'página';

    protected static ?string $pluralModelLabel = 'páginas';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function form(Schema $schema): Schema
    {
        return ContentPageForm::configure($schema);
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
        return ContentPagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentPages::route('/'),
            'edit' => EditContentPage::route('/{record}/edit'),
        ];
    }
}
