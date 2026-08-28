<?php

namespace App\Filament\Resources\Eventos\Schemas;

use App\Filament\Componentes\ImagenOptimizada;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventoForm
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

                        Textarea::make('descripcion')
                            ->label('Descripción')
                            ->rows(4)
                            ->columnSpanFull(),

                        DatePicker::make('fecha_inicio')
                            ->label('Empieza')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('fecha_fin')
                            ->label('Termina')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            // No puede acabar antes de empezar; sin esto la ficha
                            // publicaria un rango imposible sin quejarse.
                            ->afterOrEqual('fecha_inicio'),
                    ]),

                Section::make('Enlace e imagen')
                    ->columns(2)
                    ->schema([
                        TextInput::make('url')
                            ->label('Enlace')
                            ->url()
                            ->maxLength(255),

                        Select::make('tipo_url')
                            ->label('Tipo de enlace')
                            ->native(false)
                            ->options([
                                'inscripcion' => 'Inscripción',
                                'informacion' => 'Información',
                                'transmision' => 'Transmisión',
                            ])
                            ->helperText('Rotula el botón en la tarjeta del evento.'),

                        ImagenOptimizada::make('imagen', 'eventos', 1200),
                    ]),

                Section::make('Publicación')
                    ->columns(2)
                    ->schema([
                        Toggle::make('activo')
                            ->label('Visible en el sitio')
                            ->default(true),

                        TextInput::make('orden')
                            ->integer()
                            ->default(0)
                            ->helperText('Menor número, más arriba.'),
                    ]),
            ]);
    }
}
