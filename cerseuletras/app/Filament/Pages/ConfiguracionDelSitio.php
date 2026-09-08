<?php

namespace App\Filament\Pages;

use App\Filament\Componentes\ImagenOptimizada;
use App\Models\SiteSetting;
use App\Models\TipoOferta;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Configuración del sitio.
 *
 * No es un recurso porque no hay nada que listar: `site_settings` tiene una
 * sola fila y siempre la misma. Un recurso habría dado un listado de un
 * elemento y un botón de crear que no debe existir.
 *
 * Son cuarenta y dos campos, así que la segmentación no es estética: sin
 * pestañas, cambiar el teléfono obliga a recorrer los titulares de la portada,
 * los heros de los tres tipos y las seis redes sociales. Van agrupados por la
 * pregunta que responden —quiénes somos, qué se ve al entrar, cómo nos
 * localizan— y no por el orden en que están en la tabla.
 *
 * Los heros por tipo se generan desde `TipoOferta`, no escritos uno a uno: los
 * de Especialización existían en la base pero nadie los había traído al panel,
 * y con la lista a mano ese olvido se repite con el cuarto tipo que se añada.
 */
class ConfiguracionDelSitio extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configuración';

    protected static \UnitEnum|string|null $navigationGroup = 'Ajustes';

    protected static ?string $title = 'Configuración del sitio';

    protected static ?int $navigationSort = 100;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteSetting::query()->firstOrCreate([])->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Identidad')->schema($this->identidad()),
                        Tab::make('Portada')->schema($this->portada()),
                        Tab::make('Oferta')->schema($this->heros()),
                        Tab::make('Contacto')->schema($this->contacto()),
                        Tab::make('Redes')->schema($this->redes()),
                    ]),
            ])
            ->statePath('data');
    }

    /** @return list<mixed> */
    private function identidad(): array
    {
        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('site_name')->label('Nombre del sitio')->maxLength(255),
                    Textarea::make('site_description')
                        ->label('Descripción')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('Sale en el pie y en lo que se ve al compartir un enlace.'),

                    ImagenOptimizada::make('logo_path', 'sitio', 600, 'Logotipo'),
                    ImagenOptimizada::make('favicon_path', 'sitio', 512, 'Favicon'),

                    TextInput::make('header_text')->label('Texto de cabecera')->maxLength(255),
                    TextInput::make('footer_text')->label('Texto de pie')->maxLength(255),
                ]),
        ];
    }

    /** @return list<mixed> */
    private function portada(): array
    {
        return [
            Section::make('Lo primero que se lee')
                ->columns(2)
                ->schema([
                    TextInput::make('home_hero_kicker')->label('Antetítulo')->maxLength(255),
                    TextInput::make('home_hero_titulo')->label('Titular')->maxLength(255),
                    Textarea::make('home_hero_texto')->label('Texto')->rows(3)->columnSpanFull(),
                ]),

            Section::make('Botones del hero')
                ->columns(2)
                ->schema([
                    TextInput::make('home_hero_cta1_texto')->label('Botón 1 · texto')->maxLength(255),
                    TextInput::make('home_hero_cta1_url')->label('Botón 1 · enlace')->maxLength(255),
                    TextInput::make('home_hero_cta2_texto')->label('Botón 2 · texto')->maxLength(255),
                    TextInput::make('home_hero_cta2_url')->label('Botón 2 · enlace')->maxLength(255),
                ]),

            Section::make('Cifras y anuncios')
                ->columns(3)
                ->schema([
                    TextInput::make('home_stat_docentes')
                        ->label('Docentes que se anuncian')
                        ->integer()
                        ->helperText('Vacío usa el número de fichas publicadas. La Unidad cuenta los RENACYT, que no son todas.'),

                    TextInput::make('popup_retardo_ms')
                        ->label('Retardo del anuncio (ms)')
                        ->integer()
                        ->minValue(0)
                        // Pasados unos veinte segundos ya no queda nadie
                        // esperando: un retardo mayor equivale a apagar el
                        // anuncio, pero sin que se note que esta apagado.
                        ->maxValue(20000)
                        ->helperText('Entre 0 y 20000 (20 segundos).'),

                    Select::make('popup_frecuencia')
                        ->label('Cada cuánto se muestra')
                        ->native(false)
                        ->options([
                            'siempre' => 'Cada visita',
                            'sesion' => 'Una vez por sesión',
                            'dia' => 'Una vez al día',
                        ]),

                    /*
                     * Interruptor, no milisegundos. La columna es `tinyint(1)`
                     * y la API la sirve como booleano; el sitio rota las
                     * laminas cada 4 segundos fijos cuando esta activo. Pintado
                     * como campo de milisegundos, escribir «3000» aqui no
                     * cambiaba el ritmo —solo lo encendia— y cualquier valor
                     * por encima de 127 no cabe en la columna.
                     */
                    Toggle::make('popup_auto_avance')
                        ->label('Pasar las láminas solo')
                        ->helperText('Cambia de anuncio cada 4 segundos. Solo aplica si hay más de uno.'),
                ]),
        ];
    }

    /**
     * Un bloque por tipo de oferta, generado desde el enum.
     *
     * @return list<mixed>
     */
    private function heros(): array
    {
        return array_map(
            fn (TipoOferta $tipo) => Section::make($tipo->plural())
                ->description("Encabezado de la página /{$tipo->slug()}.")
                ->columns(2)
                ->schema([
                    TextInput::make($tipo->slug() . '_hero_titulo')->label('Titular')->maxLength(255),
                    TextInput::make($tipo->slug() . '_hero_claim')->label('Claim')->maxLength(255),
                    Textarea::make($tipo->slug() . '_hero_texto')->label('Texto')->rows(3)->columnSpanFull(),
                    ImagenOptimizada::make($tipo->slug() . '_hero_imagen', 'sitio', 1600, 'Imagen')
                        ->columnSpanFull(),
                ]),
            TipoOferta::cases()
        );
    }

    /** @return list<mixed> */
    private function contacto(): array
    {
        return [
            Section::make('Cómo localizan al CERSEU')
                ->columns(2)
                ->schema([
                    TextInput::make('email')->label('Correo general')->email()->maxLength(255),
                    TextInput::make('email_admision')->label('Correo de admisión')->email()->maxLength(255),
                    TextInput::make('email_tramites')->label('Correo de trámites')->email()->maxLength(255),
                    TextInput::make('telefono')->label('Teléfono')->maxLength(255),
                    TextInput::make('anexo')->label('Anexo')->maxLength(50),
                    TextInput::make('horario_atencion')->label('Horario')->maxLength(255),
                    Textarea::make('direccion')->label('Dirección')->rows(2)->columnSpanFull(),
                ]),

            Section::make('La Facultad')
                ->description('Los accesos que ocupan la barra superior del sitio.')
                ->columns(2)
                ->schema([
                    TextInput::make('web_facultad')->label('Web de la Facultad')->url()->maxLength(255),
                    TextInput::make('directorio_facultad')->label('Directorio')->url()->maxLength(255),
                ]),
        ];
    }

    /** @return list<mixed> */
    private function redes(): array
    {
        return [
            Section::make()
                ->description('Las que se dejen vacías no aparecen en el pie.')
                ->columns(2)
                ->schema([
                    TextInput::make('facebook')->url()->maxLength(255),
                    TextInput::make('instagram')->url()->maxLength(255),
                    TextInput::make('tiktok')->label('TikTok')->url()->maxLength(255),
                    TextInput::make('youtube')->label('YouTube')->url()->maxLength(255),
                    TextInput::make('linkedin')->label('LinkedIn')->url()->maxLength(255),
                    TextInput::make('twitter')->label('X (Twitter)')->url()->maxLength(255),
                ]),
        ];
    }

    /**
     * Filament 5 no pinta el formulario solo: la pagina declara su contenido, y
     * `EmbeddedSchema` es lo que enchufa el formulario dentro de un <form> con
     * su boton. Es el mismo patron que usa el propio Filament en su pagina de
     * perfil; escribir una vista Blade a mano funcionaba tambien, pero dejaba
     * fuera cosas que este componente trae —el atajo de teclado, la barra de
     * acciones fija al hacer scroll— y que habria que rehacer.
     */
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
        SiteSetting::query()->firstOrCreate([])->update($this->form->getState());

        Notification::make()
            ->title('Configuración guardada')
            // El sitio es estático: guardar no basta, hay que reconstruirlo.
            // Decirlo aquí evita la pregunta de por qué no se ve el cambio.
            ->body('El sitio se reconstruye solo; el cambio tarda unos minutos en verse.')
            ->success()
            ->send();
    }
}
