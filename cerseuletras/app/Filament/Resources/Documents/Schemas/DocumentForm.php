<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Models\Document;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                // `type` es texto libre en la base, no un enum. Se sugieren los
                // ya usados en vez de fijar una lista: si la Unidad inventa una
                // categoria nueva, el panel no se lo impide.
                TextInput::make('type')
                    ->label('Tipo')
                    ->required()
                    ->maxLength(50)
                    ->datalist(fn () => Document::query()->distinct()->pluck('type')->all()),

                FileUpload::make('url')
                    ->label('Archivo')
                    ->disk('public')
                    ->directory('documentos')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    // El nombre original se conserva para enseñarlo al
                    // descargar: el fichero se guarda con un nombre aleatorio.
                    ->storeFileNamesIn('original_name'),

                Toggle::make('published')
                    ->label('Publicado')
                    ->default(true),
            ]);
    }
}
