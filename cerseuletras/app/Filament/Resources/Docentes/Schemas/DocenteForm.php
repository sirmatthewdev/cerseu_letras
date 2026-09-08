<?php

namespace App\Filament\Resources\Docentes\Schemas;

use App\Filament\Componentes\ImagenOptimizada;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Formulario de docente.
 *
 * El generador de Filament produce un campo de texto por columna, que para
 * `foto`, `lineas_investigacion` o `grupo_investigacion` significa pedirle a
 * quien edita que escriba a mano una ruta de fichero o un JSON. Esto recupera
 * lo que hacía el formulario escrito a mano, que sí sabía qué es cada columna.
 */
class DocenteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombres')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('apellidos')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('grado')
                            ->label('Grado académico')
                            ->maxLength(100)
                            ->placeholder('Dr., Mg., Lic.…'),

                        Toggle::make('estado')
                            ->label('Visible en el sitio')
                            ->helperText('Si se apaga, la ficha deja de aparecer en la plana docente.')
                            ->default(true),

                        // El slug no se edita: lo genera el modelo al guardar, y
                        // lo rehace si cambian nombres o apellidos. Enseñarlo
                        // editable invitaría a tocarlo y romper enlaces ya
                        // publicados sin que nada avise.
                    ]),

                Section::make('Fotografía')
                    ->schema([
                        /*
                         * El mismo componente que el resto de imagenes del
                         * panel. Estaba copiado a mano aqui, y la copia se
                         * quedo con el fallo que la original ya no tiene: el
                         * cierre de guardado nombraba su parametro `$archivo`,
                         * y Filament lo empareja por nombre —espera `$file`—,
                         * asi que subir la foto de un docente moria al guardar.
                         */
                        ImagenOptimizada::make('foto', 'docentes', 800, 'Foto'),
                    ]),

                Section::make('Trayectoria')
                    ->schema([
                        Textarea::make('biografia')
                            ->label('Biografía')
                            ->rows(6)
                            ->columnSpanFull(),

                        // Un array de cadenas. El formulario anterior era un
                        // textarea que se partía por saltos de línea; aquí cada
                        // línea es una etiqueta, así que no hay que adivinar el
                        // separador.
                        TagsInput::make('lineas_investigacion')
                            ->label('Líneas de investigación')
                            ->placeholder('Añadir línea')
                            ->columnSpanFull(),
                    ]),

                Section::make('Grupo de investigación')
                    ->columns(2)
                    ->schema([
                        TextInput::make('grupo_investigacion.nombre')
                            ->label('Nombre del grupo')
                            ->maxLength(255),

                        TextInput::make('grupo_investigacion.link')
                            ->label('Enlace')
                            ->url()
                            ->maxLength(255),
                    ]),

                Section::make('Enlaces y contacto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('orcid')
                            ->label('ORCID')
                            ->maxLength(255),

                        TextInput::make('cti_vitae')
                            ->label('CTI Vitae')
                            ->url(),

                        TextInput::make('linkedin')
                            ->label('LinkedIn')
                            ->url(),
                    ]),

                Section::make('Programas que dicta')
                    ->schema([
                        Select::make('programas')
                            ->label('Programas')
                            ->relationship('programas', 'nombre')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('El orden y la coordinación se siguen editando desde la ficha del programa.'),
                    ]),
            ]);
    }
}
