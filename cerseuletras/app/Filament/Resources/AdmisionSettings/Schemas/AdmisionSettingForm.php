<?php

namespace App\Filament\Resources\AdmisionSettings\Schemas;

use App\Filament\Componentes\ImagenOptimizada;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Proceso de admisión de un tipo de oferta.
 *
 * Hay una fila por tipo y no se crean ni se borran: las siembra el seeder desde
 * el enum. Por eso el recurso solo lista y edita.
 *
 * **Hoy los tres están en blanco a propósito.** Traían el proceso entero de la
 * Unidad de Posgrado —grado de bachiller, entrevista personal— y se vació en vez
 * de dejar publicado un requisito que el CERSEU no pide. Este formulario es
 * donde la Unidad escribe el suyo.
 *
 * La forma de los JSON la fija quien los lee: `pasos` son objetos con `titulo`,
 * `descripcion` y `numero`; `requisitos_lista` y `pago_instrucciones`, listas de
 * texto. Escribir otra cosa no falla al guardar — falla en la página, y sin
 * avisar.
 */
class AdmisionSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Encabezado')->schema([
                            Section::make()->columns(2)->schema([
                                TextInput::make('hero_titulo')->label('Titular')->maxLength(255),
                                TextInput::make('hero_subtitulo')->label('Subtítulo')->maxLength(255),
                                ImagenOptimizada::make('hero_imagen', 'admision', 1600)->columnSpanFull(),
                            ]),
                        ]),

                        Tab::make('Proceso')->schema([
                            Section::make('Pasos')
                                ->description('Se numeran solos si no se indica el número.')
                                ->schema([
                                    Repeater::make('pasos')
                                        ->hiddenLabel()
                                        ->addActionLabel('Añadir paso')
                                        ->defaultItems(0)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['titulo'] ?? null)
                                        ->schema([
                                            TextInput::make('titulo')->label('Título')->required(),
                                            Textarea::make('descripcion')->label('Descripción')->rows(2),
                                            TextInput::make('numero')->label('Número')->integer(),
                                        ]),
                                ]),

                            Section::make('Requisitos')->schema([
                                TagsInput::make('requisitos_lista')
                                    ->label('Lista de requisitos')
                                    ->placeholder('Añadir requisito')
                                    ->columnSpanFull(),

                                Textarea::make('requisitos_observaciones')->label('Observaciones')->rows(3),
                                Textarea::make('requisitos_notas')->label('Notas')->rows(3),
                                TextInput::make('requisitos_email')->label('Correo para enviar requisitos')->email(),
                            ]),
                        ]),

                        Tab::make('Pago')->schema([
                            Section::make()->columns(2)->schema([
                                TextInput::make('pago_costo')->label('Costo')->maxLength(255),
                                TextInput::make('pago_link_sanmarket')->label('Enlace de SanMarket')->url(),
                                Textarea::make('pago_descripcion')->label('Descripción')->rows(3)->columnSpanFull(),
                                TagsInput::make('pago_instrucciones')
                                    ->label('Instrucciones')
                                    ->placeholder('Añadir instrucción')
                                    ->columnSpanFull(),
                                Textarea::make('pago_observaciones')->label('Observaciones')->rows(3)->columnSpanFull(),
                            ]),
                        ]),

                        Tab::make('Resultados y contacto')->schema([
                            Section::make('Resultados')->columns(2)->schema([
                                Textarea::make('resultados_texto')->label('Texto')->rows(3)->columnSpanFull(),
                                TextInput::make('resultados_enlace')->label('Enlace')->url(),
                                TextInput::make('resultados_pdf_url')->label('PDF')->maxLength(255),
                            ]),

                            Section::make('Contacto para este proceso')
                                ->description('Si se deja vacío se usa el contacto general del sitio.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('contacto_correo')->label('Correo')->email(),
                                    TextInput::make('contacto_telefono')->label('Teléfono'),
                                    TextInput::make('contacto_whatsapp')->label('WhatsApp'),
                                    TextInput::make('contacto_sitio_web')->label('Sitio web')->url(),
                                    Textarea::make('contacto_direccion')->label('Dirección')->rows(2)->columnSpanFull(),
                                    ImagenOptimizada::make('contacto_qr_path', 'admision', 600, 'Código QR'),
                                ]),
                        ]),
                    ]),
            ]);
    }
}
