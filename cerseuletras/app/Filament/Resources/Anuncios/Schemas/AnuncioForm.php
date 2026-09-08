<?php

namespace App\Filament\Resources\Anuncios\Schemas;

use App\Filament\Componentes\ImagenOptimizada;
use App\Models\Anuncio;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Anuncios emergentes de la portada.
 *
 * `imagen_ancho` e `imagen_alto` no aparecen en el formulario: los calcula el
 * trait `MideLaImagen` al guardar, leyendo el fichero ya optimizado. De esas dos
 * cifras depende que la portada reserve el hueco exacto y no de un salto
 * mientras carga la imagen, asi que pedirlas a mano seria una invitacion a
 * escribir el numero equivocado.
 */
class AnuncioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        ImagenOptimizada::make('imagen', 'anuncios', 1200)
                            // La medida recomendada, dicha donde se elige el
                            // fichero: el marco de la portada es 4:5 y lo que
                            // no la cumpla se recorta.
                            ->helperText(
                                'Recomendado: ' . Anuncio::ANCHO_RECOMENDADO . ' × '
                                . Anuncio::ALTO_RECOMENDADO . ' px (proporción 4:5). '
                                . 'Se convierte a WebP y se reduce a 1200 px de ancho.'
                            )
                            ->columnSpanFull(),

                        TextInput::make('alt')
                            ->label('Texto alternativo')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Lo que se lee en voz alta cuando la imagen no se ve. Sin esto, quien use lector de pantalla no sabe qué anuncia.'),

                        TextInput::make('link')
                            ->label('Enlace')
                            ->url()
                            ->maxLength(255),

                        TextInput::make('link_texto')
                            ->label('Texto del botón')
                            ->maxLength(255)
                            ->placeholder('Ver más'),
                    ]),

                Section::make('Cuándo se muestra')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('visible_desde')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('visible_hasta')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('visible_desde'),

                        TextInput::make('orden')->integer()->default(0),

                        Toggle::make('is_visible')
                            ->label('Activo')
                            ->default(true)
                            ->helperText('Aun estando activo, solo sale dentro de las fechas indicadas.'),
                    ]),
            ]);
    }
}
