<?php

namespace App\Filament\Pages;

use App\Models\Anuncio;
use App\Models\DirectorioCerseu;
use App\Models\Docente;
use App\Models\Document;
use App\Models\Evento;
use App\Models\Informativo;
use App\Models\Programa;
use App\Models\Testimonio;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Papelera única del panel.
 *
 * Antes un borrado era definitivo en todos los modelos. Ahora salen de la vista
 * pública pero quedan aquí, en un solo sitio — que es la decisión que importa:
 * ocho papeleras separadas, una por sección, serían peor que no tener ninguna,
 * porque nadie recuerda desde qué pantalla borró algo hace tres semanas.
 *
 * Es una página y no un recurso porque no hay un modelo detrás: son ocho, y una
 * tabla de Filament va atada a uno. Se usa `records()`, que acepta una colección
 * propia en vez de una consulta de Eloquent.
 *
 * **No hay borrado definitivo, a propósito.** Una papelera de la que se puede
 * vaciar deja de ser una red de seguridad y pasa a ser un segundo sitio donde
 * perder cosas. Si algún día hace falta purgar, que sea un comando con fecha, no
 * un botón al lado de «restaurar».
 */
class Papelera extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static ?string $navigationLabel = 'Papelera';

    protected static \UnitEnum|string|null $navigationGroup = 'Ajustes';

    protected static ?string $title = 'Papelera';

    protected static ?int $navigationSort = 99;

    /**
     * Qué se puede recuperar, y cómo se llama cada cosa en la lista.
     *
     * `titulo` es el campo —o la unión de campos— que identifica el registro
     * para una persona. El id no sirve: nadie borró «el 47».
     *
     * @var array<string, array{modelo: class-string<Model>, etiqueta: string, titulo: string|list<string>}>
     */
    private const RECUPERABLES = [
        'programas' => ['modelo' => Programa::class, 'etiqueta' => 'Programa', 'titulo' => 'nombre'],
        'docentes' => ['modelo' => Docente::class, 'etiqueta' => 'Docente', 'titulo' => ['nombres', 'apellidos']],
        'eventos' => ['modelo' => Evento::class, 'etiqueta' => 'Evento', 'titulo' => 'titulo'],
        'informativos' => ['modelo' => Informativo::class, 'etiqueta' => 'Informativo', 'titulo' => 'titulo'],
        'documentos' => ['modelo' => Document::class, 'etiqueta' => 'Documento', 'titulo' => 'original_name'],
        'testimonios' => ['modelo' => Testimonio::class, 'etiqueta' => 'Testimonio', 'titulo' => 'nombre'],
        'directorio' => ['modelo' => DirectorioCerseu::class, 'etiqueta' => 'Directorio', 'titulo' => 'nombre_persona'],
        'anuncios' => ['modelo' => Anuncio::class, 'etiqueta' => 'Anuncio', 'titulo' => 'titulo'],
    ];

    /**
     * Filament 5 no pinta la tabla sola en una pagina: hay que declararla en el
     * contenido, igual que hace `ListRecords`. Sin esto la pagina responde 200 y
     * sale vacia — que es exactamente lo que paso al escribirla.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->borrados())
            ->columns([
                TextColumn::make('titulo')
                    ->label('Qué se borró')
                    ->weight('semibold')
                    ->wrap(),

                TextColumn::make('etiqueta')->label('Tipo')->badge(),

                TextColumn::make('borrado')
                    ->label('Cuándo')
                    ->dateTime('d/m/Y H:i')
                    // Cuánto lleva borrado importa más que la fecha exacta.
                    // El parámetro tiene que llamarse `$record`: Filament los
                    // inyecta por nombre, y con otro nombre no los resuelve.
                    ->description(fn (array $record): string => $record['hace']),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(collect(self::RECUPERABLES)->map(fn (array $c): string => $c['etiqueta'])->all()),
            ])
            ->recordActions([
                Action::make('restaurar')
                    ->label('Restaurar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->modalDescription('Vuelve a estar donde estaba. Si era visible, vuelve a verse en el sitio.')
                    ->action(fn (array $record) => $this->restaurar($record['tipo'], (int) $record['modelo_id'])),
            ])
            ->emptyStateHeading('La papelera está vacía')
            ->emptyStateDescription('Lo que se borre desde el panel aparecerá aquí y se podrá recuperar.');
    }

    /**
     * Todo lo borrado, de los ocho modelos, ordenado por cuándo se borró.
     *
     * @return Collection<string, array<string, mixed>>
     */
    private function borrados(): Collection
    {
        $tipo = $this->tableFilters['tipo']['value'] ?? null;

        return collect(self::RECUPERABLES)
            ->when($tipo, fn (Collection $c): Collection => $c->only([$tipo]))
            ->flatMap(function (array $config, string $clave): array {
                return $config['modelo']::onlyTrashed()
                    ->orderByDesc('deleted_at')
                    // Un tope por modelo: la papelera es para recuperar algo
                    // reciente, no para recorrer el historial entero.
                    ->limit(100)
                    ->get()
                    ->map(fn (Model $registro): array => [
                        // La clave incluye el tipo: dos modelos distintos
                        // pueden tener el id 3, y sin esto uno taparía al otro.
                        'id' => $clave . '-' . $registro->getKey(),
                        'tipo' => $clave,
                        'modelo_id' => $registro->getKey(),
                        'etiqueta' => $config['etiqueta'],
                        'titulo' => $this->titulo($registro, $config['titulo']),
                        'borrado' => $registro->deleted_at,
                        'hace' => $registro->deleted_at?->diffForHumans() ?? '',
                    ])
                    ->all();
            })
            ->sortByDesc('borrado')
            ->keyBy('id');
    }

    private function restaurar(string $tipo, int $id): void
    {
        $config = self::RECUPERABLES[$tipo] ?? null;

        if (! $config) {
            return;
        }

        $registro = $config['modelo']::onlyTrashed()->find($id);

        if (! $registro) {
            Notification::make()
                ->title('Ya no está en la papelera')
                ->body('Puede que alguien lo haya restaurado antes.')
                ->warning()
                ->send();

            return;
        }

        $registro->restore();

        Notification::make()
            ->title($config['etiqueta'] . ' restaurado')
            ->body('Vuelve a estar donde estaba.')
            ->success()
            ->send();
    }

    /**
     * @param  string|list<string>  $campos
     */
    private function titulo(Model $registro, string|array $campos): string
    {
        $partes = collect((array) $campos)
            ->map(fn (string $campo): string => trim((string) $registro->{$campo}))
            ->filter();

        // Un registro sin nombre existe —un docente a medio crear, por
        // ejemplo—, y dejarlo en blanco lo haría irrecuperable de hecho.
        return $partes->isEmpty()
            ? '(sin nombre) #' . $registro->getKey()
            : $partes->implode(' ');
    }
}
