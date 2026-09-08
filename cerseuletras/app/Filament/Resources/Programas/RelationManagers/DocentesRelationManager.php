<?php

namespace App\Filament\Resources\Programas\RelationManagers;

use App\Models\Docente;
use App\Models\Programa;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Los docentes de un programa, con lo que la relación guarda de cada uno.
 *
 * Existe porque el formulario del programa traía un selector múltiple, y un
 * selector solo sabe enganchar. La relación lleva cuatro datos propios
 * —`es_coordinador`, `coordinador_denominacion`, `rol` y `orden`— que la ficha
 * pública sí muestra: quién coordina el programa y cómo se le nombra. Con el
 * selector, esos cuatro campos no se podían tocar desde este panel; se ponían
 * desde el anterior, y al retirarlo se habrían quedado sin ninguna puerta.
 *
 * Un *relation manager* y no un repetidor en el formulario: en una relación de
 * muchos a muchos, cada fila del repetidor **es** el docente, así que no hay
 * dónde elegirlo. Filament resuelve este caso justo aquí, con acciones de
 * enganchar y soltar que sí saben del pivote.
 */
class DocentesRelationManager extends RelationManager
{
    protected static string $relationship = 'docentes';

    protected static ?string $title = 'Docentes';

    /** Los campos del pivote, compartidos por enganchar y editar. */
    private static function camposDelPivote(): array
    {
        return [
            TextInput::make('rol')
                ->label('Rol')
                ->maxLength(255)
                ->placeholder('Docente'),

            TextInput::make('orden')
                ->label('Orden')
                ->integer()
                ->default(0)
                ->helperText('Menor número, antes en la ficha.'),

            Toggle::make('es_coordinador')
                ->label('Coordina el programa')
                ->live(),

            // Solo para quien coordina: en el resto de filas se guardaba y no
            // se usaba en ninguna parte.
            Select::make('coordinador_denominacion')
                ->label('Cómo se le nombra')
                ->options(array_combine(
                    Programa::DENOMINACIONES_COORDINADOR,
                    Programa::DENOMINACIONES_COORDINADOR
                ))
                ->native(false)
                ->visible(fn (callable $get): bool => (bool) $get('es_coordinador'))
                ->placeholder(Programa::DENOMINACIONES_COORDINADOR[0]),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::camposDelPivote());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('apellidos')
            ->columns([
                TextColumn::make('apellidos')
                    ->label('Docente')
                    ->state(fn (Docente $record): string => "{$record->apellidos}, {$record->nombres}")
                    ->searchable(['apellidos', 'nombres']),

                TextColumn::make('rol')
                    ->label('Rol')
                    ->state(fn (Docente $record): string => $record->pivot->rol ?: 'Docente'),

                IconColumn::make('es_coordinador')
                    ->label('Coordina')
                    ->boolean()
                    ->state(fn (Docente $record): bool => (bool) $record->pivot->es_coordinador),

                TextColumn::make('coordinador_denominacion')
                    ->label('Denominación')
                    ->placeholder('—')
                    ->state(fn (Docente $record): ?string => $record->pivot->es_coordinador
                        ? Programa::denominacionCoordinador($record->pivot->coordinador_denominacion)
                        : null),

                TextColumn::make('orden')
                    ->label('Orden')
                    ->state(fn (Docente $record): int => (int) $record->pivot->orden),
            ])
            ->defaultSort('docente_programa.orden')
            ->headerActions([
                AttachAction::make()
                    ->label('Añadir docente')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->orderBy('apellidos'))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...self::camposDelPivote(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DetachAction::make()->label('Quitar'),
            ]);
    }
}
