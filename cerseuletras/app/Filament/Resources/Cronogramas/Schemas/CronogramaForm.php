<?php

namespace App\Filament\Resources\Cronogramas\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Cronograma académico.
 *
 * Las filas del cronograma no son todas iguales: `is_section_heading` marca las
 * que son un encabezado —«Primer semestre»— y no una actividad con fecha. Se
 * distingue con un interruptor porque, si no, alguien acaba escribiendo el
 * encabezado como una actividad con la fecha vacía y la tabla del sitio lo pinta
 * como una fila más.
 */
class CronogramaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('code')
                            ->label('Código')
                            ->maxLength(50)
                            ->helperText('Identificador interno; el sitio busca el cronograma por él.'),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(2)
                            ->columnSpanFull(),

                        DatePicker::make('effective_date')
                            ->label('Vigente desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->helperText('Solo el activo se publica.'),
                    ]),

                Section::make('Filas')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->addActionLabel('Añadir fila')
                            ->defaultItems(0)
                            ->collapsed()
                            ->orderColumn('orden')
                            ->itemLabel(fn (array $state): ?string => $state['actividad'] ?? $state['section'] ?? null)
                            ->columns(2)
                            ->schema([
                                Toggle::make('is_section_heading')
                                    ->label('Es un encabezado')
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('section')
                                    ->label('Encabezado')
                                    ->maxLength(255)
                                    ->columnSpanFull()
                                    ->visible(fn ($get): bool => (bool) $get('is_section_heading')),

                                TextInput::make('actividad')
                                    ->label('Actividad')
                                    ->maxLength(255)
                                    ->visible(fn ($get): bool => ! $get('is_section_heading')),

                                TextInput::make('fecha_text')
                                    ->label('Fecha')
                                    ->maxLength(255)
                                    ->placeholder('Del 3 al 14 de marzo')
                                    ->helperText('Texto libre: la Unidad la escribe como la publica.')
                                    ->visible(fn ($get): bool => ! $get('is_section_heading')),
                            ]),
                    ]),
            ]);
    }
}
