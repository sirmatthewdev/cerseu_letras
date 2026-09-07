<?php

namespace App\Filament\Pages;

use App\Models\CronogramaAdmision;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * El bloque «Cómo inscribirte» de la portada.
 *
 * Es una página y no un recurso porque `cronograma_admisiones` tiene una sola
 * fila: `CronogramaAdmision::get()` devuelve `first()`. Un recurso habría dado
 * un listado de un elemento con un botón de crear que sobra.
 *
 * Tres cosas que no son evidentes y que hay que respetar:
 *
 * 1. **Las fechas son texto, no fechas.** `fecha_inicio` y `fecha_fin` se
 *    guardan como cadenas y el accesor las une con un guion. La Unidad publica
 *    «Del 3 al 14 de marzo» o «Marzo 2026», y un selector de calendario obligaría
 *    a inventar un día concreto donde no lo hay.
 *
 * 2. **El icono sale del catálogo del modelo.** `CronogramaAdmision::ICONOS` son
 *    trazados de Heroicons que se dibujan en línea; el sitio recibe el trazado ya
 *    resuelto. Escribir la clave a mano dejaría el paso sin icono sin avisar.
 *
 * 3. **La caché.** El bloque se sirve cacheado una hora. Guardar sin olvidarla
 *    deja la portada mostrando lo anterior, y quien edita concluye que no se ha
 *    guardado.
 */
class CronogramaDeAdmision extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Cómo inscribirte';

    protected static \UnitEnum|string|null $navigationGroup = 'Admisión';

    protected static ?string $title = 'Cómo inscribirte (portada)';

    protected static ?int $navigationSort = 45;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $bloque = CronogramaAdmision::query()->with('pasos')->firstOrCreate([]);

        $this->form->fill([
            ...$bloque->attributesToArray(),
            'pasos' => $bloque->pasos->sortBy('orden')->values()->map(
                fn ($paso) => $paso->only([
                    'titulo', 'fecha_inicio', 'fecha_fin', 'detalle',
                    'publico', 'icono', 'destacado', 'is_visible',
                ])
            )->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Encabezado')
                    ->columns(2)
                    ->schema([
                        TextInput::make('eyebrow')
                            ->label('Antetítulo')
                            ->maxLength(255)
                            ->placeholder('Formación abierta a la comunidad'),

                        TextInput::make('titulo')
                            ->label('Título')
                            ->maxLength(255)
                            ->placeholder('Cómo inscribirte'),

                        TextInput::make('boton_texto')
                            ->label('Texto del botón')
                            ->maxLength(255)
                            ->helperText('Sin texto, el botón no aparece.'),

                        TextInput::make('boton_url')
                            ->label('Enlace del botón')
                            ->maxLength(255)
                            ->placeholder('/cursos'),

                        Toggle::make('is_visible')
                            ->label('Mostrar la sección en la portada')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Pasos')
                    ->description('La sección no se publica si no hay ningún paso visible.')
                    ->schema([
                        Repeater::make('pasos')
                            ->hiddenLabel()
                            ->addActionLabel('Añadir paso')
                            ->defaultItems(0)
                            ->collapsible()
                            ->reorderable()
                            ->itemLabel(fn (array $state): ?string => $state['titulo'] ?? null)
                            ->columns(2)
                            ->schema([
                                TextInput::make('titulo')
                                    ->label('Título')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                // Texto libre, no calendario: la Unidad publica
                                // «Del 3 al 14 de marzo» o «Marzo 2026».
                                TextInput::make('fecha_inicio')
                                    ->label('Desde')
                                    ->maxLength(255)
                                    ->placeholder('3 de marzo'),

                                TextInput::make('fecha_fin')
                                    ->label('Hasta')
                                    ->maxLength(255)
                                    ->placeholder('14 de marzo')
                                    ->helperText('Con las dos, el sitio las une con un guion.'),

                                Select::make('icono')
                                    ->label('Icono')
                                    ->native(false)
                                    ->options(collect(CronogramaAdmision::ICONOS)
                                        ->map(fn (array $i): string => $i['label'])
                                        ->all()),

                                TextInput::make('publico')
                                    ->label('A quién va dirigido')
                                    ->maxLength(255)
                                    ->placeholder('Público general'),

                                Textarea::make('detalle')
                                    ->label('Detalle')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Toggle::make('destacado')->label('Destacado'),
                                Toggle::make('is_visible')->label('Visible')->default(true),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->contenidoDelFormulario()]);
    }

    private function contenidoDelFormulario(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('guardar')
            ->footer([
                Actions::make($this->getFormActions())->key('form-actions'),
            ]);
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('guardar')
                ->label('Guardar cambios')
                ->submit('guardar')
                ->keyBindings(['mod+s']),
        ];
    }

    public function guardar(): void
    {
        $datos = $this->form->getState();
        $pasos = $datos['pasos'] ?? [];
        unset($datos['pasos']);

        $bloque = CronogramaAdmision::query()->firstOrCreate([]);
        $bloque->update($datos);

        /*
         * Los pasos se reescriben enteros en vez de reconciliarse uno a uno.
         *
         * Son cuatro o cinco filas sin nada que dependa de su id —el sitio los
         * lee por orden, no por clave—, así que emparejarlos añadiría un camino
         * que puede equivocarse a cambio de nada. El `orden` sale de la posición
         * en el repetidor, que es como se han arrastrado en pantalla.
         */
        $bloque->pasos()->delete();

        foreach (array_values($pasos) as $i => $paso) {
            $bloque->pasos()->create([...$paso, 'orden' => $i]);
        }

        // Sin esto, la portada sigue mostrando lo anterior durante una hora y
        // quien edita concluye que no se ha guardado.
        CronogramaAdmision::clearCache();

        Notification::make()
            ->title('Guardado')
            ->body('El sitio se reconstruye solo; el cambio tarda unos minutos en verse.')
            ->success()
            ->send();
    }
}
