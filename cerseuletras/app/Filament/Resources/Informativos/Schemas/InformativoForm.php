<?php

namespace App\Filament\Resources\Informativos\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Documentos y recursos informativos.
 *
 * `tipo` es un entero: 0 es un PDF subido y 1 un enlace externo. Se conserva tal
 * cual porque la API y el sitio lo leen asi; lo que cambia es que aqui se elige
 * con dos opciones rotuladas en vez de dos radios sin nombre, y el campo que
 * sobra desaparece en lugar de quedarse pidiendo un dato que no aplica.
 */
class InformativoForm
{
    private const TIPO_PDF = 0;
    private const TIPO_ENLACE = 1;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titulo')
                    ->label('Título')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('categoria')
                    ->label('Categoría')
                    ->maxLength(255)
                    ->datalist(fn () => \App\Models\Informativo::query()
                        ->whereNotNull('categoria')
                        ->distinct()
                        ->pluck('categoria')
                        ->all())
                    ->helperText('Agrupa los recursos en la página. Se sugieren las ya usadas.'),

                Radio::make('tipo')
                    ->label('Qué es')
                    ->required()
                    ->default(self::TIPO_PDF)
                    ->live()
                    ->options([
                        self::TIPO_PDF => 'Un PDF que se sube',
                        self::TIPO_ENLACE => 'Un enlace externo',
                    ]),

                FileUpload::make('url')
                    ->label('Documento')
                    ->disk('public')
                    ->directory('informativos')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->visible(fn ($get): bool => (int) $get('tipo') === self::TIPO_PDF)
                    ->required(fn ($get): bool => (int) $get('tipo') === self::TIPO_PDF),

                TextInput::make('url')
                    ->label('Dirección')
                    ->url()
                    ->maxLength(255)
                    ->visible(fn ($get): bool => (int) $get('tipo') === self::TIPO_ENLACE)
                    ->required(fn ($get): bool => (int) $get('tipo') === self::TIPO_ENLACE),

                TextInput::make('orden')
                    ->integer()
                    ->default(0)
                    ->helperText('Menor número, más arriba.'),
            ]);
    }
}
