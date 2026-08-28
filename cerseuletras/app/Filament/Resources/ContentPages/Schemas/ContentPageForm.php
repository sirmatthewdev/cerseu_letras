<?php

namespace App\Filament\Resources\ContentPages\Schemas;

use App\Models\ContentPage;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Páginas de contenido: Trámites, Admisión y Nosotros.
 *
 * El `slug` no es texto libre: la lista sale de `ContentPage::PAGINAS`, que es
 * la misma constante que consulta el sitio para pedir cada página. Escrito a
 * mano, un slug con una letra distinta crea una página que no lee nadie y deja
 * la de verdad sin contenido.
 *
 * Las secciones se editan aquí dentro y no en un recurso aparte: nunca existen
 * solas —una sección sin página no significa nada— y separarlas obligaría a
 * saltar entre dos pantallas para escribir un texto seguido.
 *
 * Aviso conocido: hoy /tramites tiene tres secciones que dicen literalmente
 * «pendiente de carga». Están esperando el procedimiento real de la Unidad.
 */
class ContentPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('slug')
                            ->label('Página')
                            ->required()
                            ->native(false)
                            ->options(ContentPage::PAGINAS)
                            ->unique(ignoreRecord: true)
                            ->helperText('Cada página existe una sola vez.'),

                        TextInput::make('titulo')->label('Título')->maxLength(255),

                        TextInput::make('subtitulo')
                            ->label('Subtítulo')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Secciones')
                    ->description('El cuerpo de la página, en el orden en que se lee.')
                    ->schema([
                        Repeater::make('secciones')
                            ->hiddenLabel()
                            ->relationship()
                            ->addActionLabel('Añadir sección')
                            ->defaultItems(0)
                            ->collapsible()
                            ->cloneable()
                            ->orderColumn('orden')
                            ->itemLabel(fn (array $state): ?string => trim(
                                ($state['numeral'] ?? '') . ' ' . ($state['titulo'] ?? '')
                            ) ?: null)
                            ->schema([
                                TextInput::make('numeral')
                                    ->label('Numeral')
                                    ->maxLength(20)
                                    ->placeholder('1.1'),

                                TextInput::make('titulo')->label('Título')->maxLength(255),

                                TextInput::make('grupo')
                                    ->label('Grupo')
                                    ->maxLength(255)
                                    ->helperText('Agrupa secciones bajo un mismo encabezado.'),

                                Toggle::make('is_visible')->label('Visible')->default(true),

                                Textarea::make('cuerpo')
                                    ->label('Texto')
                                    ->rows(6)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
