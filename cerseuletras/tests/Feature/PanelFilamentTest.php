<?php

namespace Tests\Feature;

use App\Filament\Widgets\ResumenDelSitio;
use App\Models\Docente;
use App\Models\Programa;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel de administración en Filament.
 *
 * Lo que se guarda aquí es el control de acceso. Filament trae su propio
 * criterio por defecto —deja entrar a cualquiera en entorno local y a nadie
 * fuera de él— y ese respaldo se aplica en silencio si `canAccessPanel()` no
 * está: un panel abierto en desarrollo no lo nota nadie hasta que alguien
 * levanta el entorno con otra bandera.
 *
 * También se comprueba que el panel de siempre sigue en pie: los dos conviven
 * mientras dura la migración, y romper `/admin` mientras se construye `/panel`
 * dejaría a la Unidad sin herramienta.
 */
class PanelFilamentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_sin_sesion_el_panel_manda_al_login(): void
    {
        $this->get('/panel')->assertRedirect('/login');
        $this->get('/panel/docentes')->assertRedirect('/login');
    }

    public function test_quien_no_es_admin_no_entra_aunque_tenga_sesion(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get('/panel')
            ->assertForbidden();
    }

    /**
     * La misma comprobación, pero en entorno local — y esta es la que de verdad
     * guarda algo.
     *
     * Se descubrió quitando `canAccessPanel()` a propósito para ver si las
     * pruebas lo notaban: la de arriba **siguió pasando**, porque el respaldo de
     * Filament niega a todo el mundo fuera de local, así que pasaba por el
     * motivo equivocado. En local ese mismo respaldo hace lo contrario: deja
     * entrar a cualquiera con sesión. Como las pruebas nunca corren en `local`,
     * el caso peligroso no lo veía nadie.
     *
     * De ahí que aquí se fuerce el entorno. Y se fuerza tocando
     * `config('app.env')`, que es lo que Filament mira de verdad —el primer
     * intento usó `detectEnvironment()` y la prueba pasaba igual sin la guarda,
     * porque el respaldo nunca llega a consultar eso.
     */
    public function test_en_local_tampoco_entra_quien_no_es_admin(): void
    {
        config(['app.env' => 'local']);

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get('/panel')
            ->assertForbidden();
    }

    public function test_un_admin_entra_y_ve_la_plana_docente(): void
    {
        Docente::create([
            'nombres' => 'Ada',
            'apellidos' => 'Lovelace',
            'estado' => 1,
        ]);

        $this->actingAs($this->admin())
            ->get('/panel/docentes')
            ->assertOk()
            ->assertSee('Lovelace');
    }

    /**
     * El criterio es uno solo: el mismo `role === 'admin'` que el middleware
     * `isAdmin` aplica a las rutas del panel de siempre. Si algún día divergen,
     * habría gente que entra por una puerta y no por la otra.
     */
    public function test_el_criterio_de_acceso_es_el_mismo_en_los_dos_paneles(): void
    {
        $admin = $this->admin();
        $normal = User::factory()->create(['role' => 'user']);

        $panel = app(\Filament\Panel::class);

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($admin->isAdmin());

        $this->assertFalse($normal->canAccessPanel($panel));
        $this->assertFalse($normal->isAdmin());
    }

    public function test_el_panel_de_siempre_sigue_funcionando(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/programas')
            ->assertOk();
    }

    /**
     * Todos los recursos abren, en listado y en creación.
     *
     * Parece poca cosa y no lo es: un campo mal declarado —un tipo de propiedad
     * que no coincide con el de Filament, una relación que no existe, un enum
     * mal escrito— no falla al arrancar la aplicación, sino al pintar esa
     * pantalla concreta. Sin esta prueba, romper un recurso solo se nota cuando
     * alguien de la Unidad entra a usarlo.
     *
     * El formulario de creación es el que más descubre: es donde se instancian
     * todos los campos.
     *
     * @return list<array{string}>
     */
    public static function recursos(): array
    {
        return [
            ['programas'],
            ['docentes'],
            ['testimonios'],
            ['eventos'],
            ['informativos'],
            ['anuncios'],
            ['documents'],
            ['directorio-cerseus'],
            ['leads'],
            ['menu-items'],
            ['users'],
            ['admision-settings'],
            ['content-pages'],
            ['cronogramas'],
        ];
    }

    /** Solicitudes es de solo lectura: no tiene formulario de creacion. */
    private const SIN_CREACION = ['leads', 'admision-settings', 'content-pages'];

    #[\PHPUnit\Framework\Attributes\DataProvider('recursos')]
    public function test_cada_recurso_abre_su_listado_y_su_formulario(string $recurso): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get("/panel/{$recurso}")->assertOk();

        if (! in_array($recurso, self::SIN_CREACION, true)) {
            $this->actingAs($admin)->get("/panel/{$recurso}/create")->assertOk();
        }
    }

    /**
     * Y que las solicitudes sigan siendo de solo lectura.
     *
     * Las crea el visitante desde el formulario del sitio; crearlas o editarlas
     * desde el panel seria inventar o falsear lo que alguien pidio. Es una
     * decision, no una pantalla a medias, asi que conviene que quede fijada.
     */
    public function test_las_solicitudes_no_se_crean_ni_se_editan(): void
    {
        $this->actingAs($this->admin())
            ->get('/panel/leads/create')
            ->assertNotFound();

        // Y que tampoco se ofrezca el boton: `canCreate()` en false no basta
        // —el `CreateAction` del generador se pintaba igual—, asi que el boton
        // salia y llevaba al 404 de arriba.
        $this->actingAs($this->admin())
            ->get('/panel/leads')
            ->assertOk()
            ->assertDontSee('Crear solicitud');
    }

    /**
     * El escritorio resume el estado del sitio.
     *
     * Se comprueba sobre el componente y no sobre el HTML de `/panel`: los
     * widgets de Filament se pintan por Livewire después de la respuesta
     * inicial, así que un `assertSee` contra esa respuesta busca un texto que
     * todavía no existe — y falla aunque el escritorio funcione, que es
     * justo lo que pasó al escribir esta prueba.
     */
    /**
     * La pagina de ajustes tambien abre.
     *
     * No es un recurso —`site_settings` tiene una sola fila— sino una pagina
     * propia, asi que se queda fuera del recorrido de arriba y necesita la suya.
     */
    public function test_la_configuracion_del_sitio_abre(): void
    {
        $this->actingAs($this->admin())
            ->get('/panel/configuracion-del-sitio')
            ->assertOk();
    }

    /**
     * Las tres paginas que no son recursos, en un solo sitio.
     *
     * Se quedan fuera del recorrido de los recursos porque no tienen listado ni
     * formulario de creacion, y sin esto un fallo en cualquiera de ellas solo se
     * nota al abrirla a mano.
     */
    public function test_las_paginas_propias_abren(): void
    {
        $admin = $this->admin();

        foreach (['configuracion-del-sitio', 'cronograma-de-admision', 'papelera'] as $pagina) {
            $this->actingAs($admin)->get("/panel/{$pagina}")->assertOk();
        }
    }

    /**
     * Y guarda de verdad.
     *
     * Que la pantalla abra no dice nada sobre si el formulario escribe: es una
     * pagina propia, con su `mount()` y su `guardar()` a mano, y ahi es facil
     * que el estado se llene de un sitio y se guarde en otro. Se comprueba el
     * viaje entero, incluido un campo de Especializacion — que es justo el que
     * `$fillable` descartaba en silencio.
     */
    public function test_la_configuracion_guarda_lo_que_se_escribe(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(\App\Filament\Pages\ConfiguracionDelSitio::class)
            ->fillForm([
                'site_name' => 'CERSEU de prueba',
                'telefono' => '900 000 000',
                'especializaciones_hero_titulo' => 'Especializaciones del CERSEU',
            ])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $ajustes = \App\Models\SiteSetting::query()->first();

        $this->assertSame('CERSEU de prueba', $ajustes->site_name);
        $this->assertSame('900 000 000', $ajustes->telefono);
        $this->assertSame('Especializaciones del CERSEU', $ajustes->especializaciones_hero_titulo);
    }

    /**
     * Todo campo del formulario apunta a algo que se puede guardar.
     *
     * Es la guarda contra el fallo mas silencioso que tiene un panel generado:
     * un campo que apunta a un ACCESOR en vez de a una columna. El formulario lo
     * pinta, quien edita lo rellena, el panel dice «guardado» y el valor no va a
     * ninguna parte. Paso con `imagen` —que se llamaba `imagen_url`, que es un
     * accesor— y antes con los heros de Especializacion, que no estaban en
     * `$fillable`.
     *
     * Se comprueba sobre `Programa`, que es el formulario con mas campos.
     */
    public function test_los_campos_del_programa_se_pueden_guardar(): void
    {
        $modelo = new Programa();
        $columnas = \Illuminate\Support\Facades\Schema::getColumnListing($modelo->getTable());
        $asignables = $modelo->getFillable();

        $esquema = \App\Filament\Resources\Programas\Schemas\ProgramaForm::configure(
            \Filament\Schemas\Schema::make(\Livewire\Livewire::test(
                \App\Filament\Resources\Programas\Pages\CreatePrograma::class
            )->instance())
        );

        foreach ($this->camposDe($esquema) as $campo) {
            // Los anidados (`inversion_economica.costo_total`) apuntan dentro de
            // un JSON: basta con que la columna raiz exista y sea asignable.
            $raiz = explode('.', $campo)[0];

            // Las relaciones no son columnas y se guardan por su cuenta.
            if (in_array($raiz, ['docentes'], true)) {
                continue;
            }

            $this->assertContains($raiz, $columnas, "El campo «{$campo}» no es una columna de programas.");
            $this->assertContains($raiz, $asignables, "El campo «{$campo}» no esta en \$fillable: se descartaria en silencio.");
        }
    }

    /**
     * Los nombres de todos los campos de un esquema, recorriendo pestanas,
     * secciones y repetidores.
     *
     * @return list<string>
     */
    private function camposDe(\Filament\Schemas\Schema $esquema): array
    {
        $nombres = [];

        $recorrer = function ($componentes) use (&$recorrer, &$nombres): void {
            foreach ($componentes as $componente) {
                if ($componente instanceof \Filament\Forms\Components\Field) {
                    $nombres[] = $componente->getName();

                    // Dentro de un repetidor los campos son claves del JSON del
                    // propio repetidor, no columnas: no se descienden.
                    if ($componente instanceof \Filament\Forms\Components\Repeater) {
                        continue;
                    }
                }

                if (method_exists($componente, 'getDefaultChildComponents')) {
                    $recorrer($componente->getDefaultChildComponents());
                }
            }
        };

        $recorrer($esquema->getComponents());

        return array_values(array_unique($nombres));
    }

    public function test_el_escritorio_resume_el_estado_del_sitio(): void
    {
        Programa::create([
            'grado' => 'Curso',
            'nombre' => 'Publicado',
            'slug' => 'publicado',
            'estado' => Programa::ESTADO_PUBLICADO,
        ]);

        Programa::create([
            'grado' => 'Curso',
            'nombre' => 'A medias',
            'slug' => 'a-medias',
            'estado' => Programa::ESTADO_BORRADOR,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ResumenDelSitio::class)
            ->assertSee('Programas publicados')
            ->assertSee('Borradores')
            // Y que las cifras sean las de verdad, no un cero de adorno.
            ->assertSee('1');

        $this->get('/panel')->assertOk();
    }
}
