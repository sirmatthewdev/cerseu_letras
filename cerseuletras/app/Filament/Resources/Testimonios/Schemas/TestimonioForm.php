<?php

namespace App\Filament\Resources\Testimonios\Schemas;

use App\Filament\Componentes\ImagenOptimizada;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TestimonioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Select::make('programa_id')
                    ->label('Programa')
                    ->relationship('programa', 'nombre')
                    ->searchable()
                    ->preload()
                    ->helperText('De qué programa habla. Puede quedarse vacío.'),

                ImagenOptimizada::make('photo', 'testimonios', 400, 'Fotografía'),

                Textarea::make('contenido')
                    ->required()
                    ->rows(6)
                    ->columnSpanFull(),

                // `estado` es un entero en la base, no un booleano. El interruptor
                // escribe true/false, asi que se traduce en los dos sentidos: sin
                // esto se guardaria un booleano en una columna que el resto del
                // codigo lee como 0/1.
                Toggle::make('estado')
                    ->label('Visible en el sitio')
                    ->default(true)
                    ->dehydrateStateUsing(fn ($state): int => $state ? 1 : 0)
                    ->formatStateUsing(fn ($state): bool => (bool) $state),
            ]);
    }
}
