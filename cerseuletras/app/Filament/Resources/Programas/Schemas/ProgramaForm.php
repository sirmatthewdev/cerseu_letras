<?php

namespace App\Filament\Resources\Programas\Schemas;

use App\Models\Programa;
use App\Filament\Componentes\ImagenOptimizada;
use App\Models\TipoOferta;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Formulario de programa.
 *
 * Es el más largo del panel: la ficha de un curso tiene datos básicos, tres
 * bloques de contenido largo, cuatro documentos y una estructura de inversión
 * con modalidades y cuotas anidadas. El formulario anterior lo resolvía con
 * JavaScript que armaba JSON a mano y lo metía en campos ocultos; aquí cada
 * estructura se edita con el componente que le corresponde y el JSON lo escribe
 * Filament.
 *
 * Va en pestañas porque de un tirón son más de treinta campos, y quien entra a
 * corregir una fecha no tiene por qué recorrerlos todos.
 *
 * **La forma de los JSON no es libre.** `ProgramaResource` (API) y los
 * accesores del modelo leen claves concretas: `inversion_economica.modalidades[]
 * .cuotas[].{etiqueta,monto,fecha}`, `inversion_economica.condiciones[].texto`,
 * `derecho_inscripcion.{bachiller_unmsm,otras_universidades}`. Escribir otra
 * forma no rompe nada al guardar — se rompe después, en silencio, cuando la
 * ficha del sitio no encuentra lo que busca.
 */
class ProgramaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Datos básicos')->schema(self::basicos()),
                        Tab::make('Contenido')->schema(self::contenido()),
                        Tab::make('Inversión')->schema(self::inversion()),
                        Tab::make('Documentos')->schema(self::documentos()),
                    ]),
            ]);
    }

    /** @return list<mixed> */
    private static function basicos(): array
    {
        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('mencion')
                        ->label('Mención')
                        ->maxLength(255),

                    Select::make('grado')
                        ->label('Tipo de oferta')
                        ->required()
                        // Del enum y no de una lista escrita aquí: es la misma
                        // fuente que usan las rutas, la API y el sitio.
                        ->options(array_combine(TipoOferta::grados(), TipoOferta::grados()))
                        ->native(false),

                    Select::make('estado')
                        ->required()
                        ->default(Programa::ESTADO_BORRADOR)
                        ->native(false)
                        ->options([
                            Programa::ESTADO_PUBLICADO => 'Publicado',
                            Programa::ESTADO_PROXIMAMENTE => 'Próximamente',
                            Programa::ESTADO_BORRADOR => 'Borrador',
                        ])
                        ->helperText('Un borrador no sale en el sitio; se puede ver con «Vista previa».'),

                    TextInput::make('modalidad')
                        ->maxLength(100)
                        ->placeholder('Presencial, Virtual…'),

                    TextInput::make('slug')
                        ->helperText('Se genera del nombre si se deja vacío. Cambiarlo rompe los enlaces ya publicados.')
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                ]),

            Section::make('Medidas')
                ->description('Cada tipo se mide con la unidad que le es propia; los que no apliquen se dejan vacíos.')
                ->columns(3)
                ->schema([
                    TextInput::make('horas_academicas')->label('Horas académicas')->integer()->minValue(0),
                    TextInput::make('sesiones')->integer()->minValue(0),
                    TextInput::make('modulos')->label('Módulos')->integer()->minValue(0),
                    TextInput::make('duracion')->label('Duración (meses)')->integer()->minValue(0),
                    TextInput::make('creditos')->label('Créditos')->integer()->minValue(0),
                    TextInput::make('vacantes')->integer()->minValue(0),
                ]),

            Section::make('Certificación e inscripción')
                ->columns(2)
                ->schema([
                    TextInput::make('grado_otorga')->label('Se otorga')->maxLength(255),
                    TextInput::make('grado_otorga_label')->label('Etiqueta')->maxLength(100),
                    TextInput::make('fecha_limite_inscripcion')
                        ->label('Fecha límite de inscripción')
                        ->maxLength(255)
                        ->helperText('Texto libre: la Unidad la escribe como la publica.'),
                    /*
                     * A la columna `imagen`, no a `imagen_url`.
                     *
                     * `imagen_url` es un ACCESOR, no una columna: resuelve la
                     * ruta guardada y cae a una foto del campus si no hay
                     * ninguna. Un campo escribiendo ahi no guarda nada y no se
                     * queja — la ficha seguia con la imagen por defecto y el
                     * panel decia «guardado».
                     */
                    ImagenOptimizada::make('imagen', 'programas', 1200, 'Imagen de la ficha')
                        ->columnSpanFull(),
                ]),

            Section::make('Docentes')
                ->schema([
                    Select::make('docentes')
                        ->relationship('docentes', 'apellidos')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->apellidos}, {$record->nombres}"),
                ]),
        ];
    }

    /** @return list<mixed> */
    private static function contenido(): array
    {
        return [
            Section::make()
                ->schema([
                    Textarea::make('sumilla')->rows(4)->columnSpanFull(),
                    Textarea::make('por_que_text')
                        ->label('¿Por qué estudiar este programa?')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            /*
             * Los cuatro bloques largos son listas, no texto corrido: el modelo
             * los castea a `array` y así los guardaba el panel anterior. Se
             * editan como etiquetas —una entrada por punto— en vez de pedir un
             * JSON escrito a mano, que era lo que hacía el formulario de antes.
             */
            Section::make('Objetivos y perfiles')
                ->schema([
                    TagsInput::make('objetivos_academicos')
                        ->label('Objetivos académicos')
                        ->placeholder('Añadir objetivo')
                        ->columnSpanFull(),

                    TagsInput::make('plan_estudios')
                        ->label('Plan de estudios')
                        ->placeholder('Añadir asignatura o módulo')
                        ->columnSpanFull(),

                    TagsInput::make('perfil_ingresante')
                        ->label('Perfil del ingresante')
                        ->placeholder('Añadir punto')
                        ->columnSpanFull(),

                    TagsInput::make('perfil_graduado')
                        ->label('Perfil del egresado')
                        ->placeholder('Añadir punto')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return list<mixed> */
    private static function inversion(): array
    {
        return [
            Section::make('Importes')
                ->columns(2)
                ->schema([
                    TextInput::make('inversion_economica.costo_total')
                        ->label('Costo total')
                        ->numeric()
                        ->prefix('S/')
                        ->helperText('Si se deja vacío se calcula desde las tarifas por crédito.'),

                    TextInput::make('inversion_economica.costo_matricula')
                        ->label('Matrícula')
                        ->numeric()
                        ->prefix('S/'),

                    TextInput::make('inversion_economica.costo_diploma')
                        ->label('Diploma')
                        ->numeric()
                        ->prefix('S/'),

                    TextInput::make('costo_por_credito')
                        ->label('Costo por crédito')
                        ->numeric()
                        ->prefix('S/'),
                ]),

            Section::make('Derecho de inscripción')
                // Las dos claves vienen heredadas de la Unidad de Posgrado, que
                // cobraba distinto a sus bachilleres. Se conservan porque la API
                // y la ficha las leen por nombre; si el CERSEU decide otra cosa,
                // hay que cambiarlas en los tres sitios a la vez.
                ->description('Estructura heredada de Posgrado. Vacíos, no se muestra el bloque.')
                ->columns(2)
                ->schema([
                    TextInput::make('inversion_economica.derecho_inscripcion.bachiller_unmsm')
                        ->label('Bachiller UNMSM')
                        ->numeric()
                        ->prefix('S/'),

                    TextInput::make('inversion_economica.derecho_inscripcion.otras_universidades')
                        ->label('Otras universidades')
                        ->numeric()
                        ->prefix('S/'),
                ]),

            Section::make('Modalidades de pago')
                ->schema([
                    Repeater::make('inversion_economica.modalidades')
                        ->hiddenLabel()
                        ->addActionLabel('Añadir modalidad')
                        ->itemLabel(fn (array $state): ?string => $state['nombre'] ?? null)
                        ->collapsible()
                        ->defaultItems(0)
                        ->schema([
                            TextInput::make('nombre')
                                ->placeholder('Pago único, Pago fraccionado…')
                                ->helperText('Si se deja vacío, el sitio lo deduce del número de cuotas.'),

                            Repeater::make('cuotas')
                                ->addActionLabel('Añadir cuota')
                                ->defaultItems(1)
                                ->columns(3)
                                ->schema([
                                    TextInput::make('etiqueta')->placeholder('Cuota 1'),
                                    TextInput::make('monto')->numeric()->prefix('S/'),
                                    TextInput::make('fecha')->placeholder('30 de abril'),
                                ]),
                        ]),
                ]),

            Section::make('Condiciones de pago')
                ->schema([
                    Repeater::make('inversion_economica.condiciones')
                        ->hiddenLabel()
                        ->addActionLabel('Añadir condición')
                        ->defaultItems(0)
                        ->simple(
                            TextInput::make('texto')
                                ->placeholder('El pago se realiza en la caja de la Facultad…')
                        ),
                ]),
        ];
    }

    /** @return list<mixed> */
    private static function documentos(): array
    {
        return [
            Section::make()
                ->columns(2)
                ->description('Rutas o direcciones de los documentos que se enlazan en la ficha.')
                ->schema([
                    TextInput::make('plan_url')->label('Plan de estudios (PDF)')->maxLength(255),
                    TextInput::make('horario_url')->label('Horario')->maxLength(255),
                    TextInput::make('brochure_url')->label('Brochure')->maxLength(255),
                    TextInput::make('admision_pdf_url')->label('Admisión (PDF)')->maxLength(255),
                ]),
        ];
    }
}
