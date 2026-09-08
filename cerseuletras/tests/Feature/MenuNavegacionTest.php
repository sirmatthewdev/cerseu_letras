<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Menú de navegación administrable.
 *
 * Lo que llega al sitio se comprueba contra /api/v1/menu, que es por donde
 * viaja desde que el sitio público es estático: antes se leía el HTML que
 * pintaba Blade. Las reglas no cambian —lo oculto no sale, lo caducado se
 * retira solo, un destino que ya no existe se omite sin tumbar la barra—,
 * solo el sitio donde se miran.
 */
class MenuNavegacionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function entrada(array $extra = []): MenuItem
    {
        return MenuItem::create($extra + [
            'etiqueta' => 'Nosotros',
            'route_name' => 'nosotros',
            'orden' => 0,
            'is_visible' => true,
        ]);
    }

    public function test_el_menu_se_pinta_en_la_barra_publica(): void
    {
        $padre = $this->entrada(['etiqueta' => 'Vida Universitaria', 'route_name' => null]);
        MenuItem::create([
            'parent_id' => $padre->id,
            'etiqueta' => 'Quiénes somos',
            'route_name' => 'nosotros',
            'orden' => 0,
            'is_visible' => true,
        ]);

        $menu = $this->getJson('/api/v1/menu')->assertOk()->json('data');

        // El árbol llega entero: la cabecera del desplegable y lo que cuelga
        // de ella. Antes esto se contaba dos veces en el HTML, una por cada
        // copia del menú; el sitio nuevo pinta las dos desde un solo recorrido.
        $this->assertSame('Vida Universitaria', $menu[0]['etiqueta']);
        $this->assertSame('Quiénes somos', $menu[0]['hijos'][0]['etiqueta']);
        $this->assertSame('/nosotros', $menu[0]['hijos'][0]['enlace']);
    }

    public function test_una_entrada_oculta_no_llega_al_sitio(): void
    {
        $this->entrada(['etiqueta' => 'Borrador interno', 'is_visible' => false]);

        $this->getJson('/api/v1/menu')->assertOk()
            ->assertJsonMissing(['etiqueta' => 'Borrador interno']);
    }

    public function test_un_hijo_oculto_no_llega_aunque_su_padre_sea_visible(): void
    {
        $padre = $this->entrada(['etiqueta' => 'Admisión', 'route_name' => null]);
        MenuItem::create([
            'parent_id' => $padre->id, 'etiqueta' => 'Vacantes 2019',
            'url' => 'https://ejemplo.pe/viejo', 'orden' => 0, 'is_visible' => false,
        ]);

        $this->getJson('/api/v1/menu')->assertOk()
            ->assertJsonMissing(['etiqueta' => 'Vacantes 2019']);
    }

    public function test_una_ruta_que_ya_no_existe_no_tumba_la_barra(): void
    {
        $this->entrada(['etiqueta' => 'Sección retirada', 'route_name' => 'ruta.que.no.existe']);

        // Sin destino resoluble el elemento se omite, pero el menú responde:
        // una entrada rota no puede dejar al sitio sin navegación.
        $menu = $this->getJson('/api/v1/menu')->assertOk()->json('data');

        $this->assertSame([], $menu);
    }

    public function test_el_enlace_externo_abre_en_pestana_nueva_con_rel_seguro(): void
    {
        $padre = $this->entrada(['etiqueta' => 'Admisión', 'route_name' => null]);
        MenuItem::create([
            'parent_id' => $padre->id, 'etiqueta' => 'Cuadro de Vacantes',
            'url' => 'https://posgrado.unmsm.edu.pe/doc/vacantes',
            'nueva_pestana' => true, 'orden' => 0, 'is_visible' => true,
        ]);

        $menu = $this->getJson('/api/v1/menu')->assertOk()->json('data');
        $hijo = $menu[0]['hijos'][0];

        $this->assertSame('https://posgrado.unmsm.edu.pe/doc/vacantes', $hijo['enlace']);
        // El `rel="noopener noreferrer"` lo pone el sitio; lo que tiene que
        // viajar por la API es la señal de que el destino es externo.
        $this->assertTrue($hijo['nueva_pestana']);
    }

    public function test_el_panel_guarda_una_entrada_con_su_padre(): void
    {
        $padre = $this->entrada(['etiqueta' => 'Admisión', 'route_name' => null]);

        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'etiqueta' => 'Vacantes 2026',
                'parent_id' => $padre->id,
                'url' => 'https://posgrado.unmsm.edu.pe/doc/v',
                'nueva_pestana' => true,
                'is_visible' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $hijo = MenuItem::where('etiqueta', 'Vacantes 2026')->firstOrFail();
        $this->assertSame($padre->id, $hijo->parent_id);
        $this->assertTrue((bool) $hijo->nueva_pestana);
    }

    /*
     * Aqui vivia `test_lo_que_no_se_envia_se_borra`. Se retira con el panel
     * Blade y no se sustituye: guardaba una regla de aquel formulario —enviaba
     * el arbol entero de una vez, asi que lo ausente se entendia como borrado—
     * y esa regla ya no existe. En Filament cada entrada se borra a mano, que
     * ademas es lo que evita el accidente que aquella semantica permitia:
     * perder medio menu por enviar un formulario a medio cargar.
     */

    /**
     * Con las dos cosas puestas, manda la ruta interna.
     *
     * Se comprueba sobre lo que llega al sitio y no sobre lo que guarda el
     * panel: es la propiedad que importa —a donde lleva el enlace— y sigue
     * siendo cierta con cualquier panel, porque vive en el modelo.
     */
    public function test_la_ruta_interna_gana_a_la_url_externa(): void
    {
        $this->entrada([
            'etiqueta' => 'Ambos',
            'route_name' => 'nosotros',
            'url' => 'https://ejemplo.pe',
        ]);

        $respuesta = $this->getJson('/api/v1/menu')->assertOk();

        $item = collect($respuesta->json('data'))->firstWhere('etiqueta', 'Ambos');
        $this->assertNotNull($item);
        $this->assertNotSame('https://ejemplo.pe', $item['enlace']);
    }

    public function test_una_direccion_externa_invalida_se_rechaza(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->fillForm(['etiqueta' => 'Rota', 'url' => 'no-es-una-url'])
            ->call('create')
            ->assertHasFormErrors(['url']);
    }

    public function test_guardar_desde_el_panel_invalida_la_cache_del_menu(): void
    {
        $this->entrada(['etiqueta' => 'Antes']);
        // Deja el árbol en caché.
        $this->getJson('/api/v1/menu')->assertJsonFragment(['etiqueta' => 'Antes']);

        Livewire::test(CreateMenuItem::class)
            ->fillForm(['etiqueta' => 'Después', 'route_name' => 'nosotros', 'is_visible' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        // Sin invalidar, el menú nuevo no se vería hasta que caducara la caché:
        // se guarda, se mira el sitio, no ha cambiado nada, y se vuelve a
        // guardar pensando que no se guardó.
        $this->getJson('/api/v1/menu')->assertJsonFragment(['etiqueta' => 'Después']);
    }

    public function test_una_cabecera_con_hijos_conserva_su_propio_destino(): void
    {
        // La cabecera de un desplegable puede llevar a algún sitio además de
        // desplegar: sin su enlace no habría forma de llegar a /cursos desde el
        // menú, solo a sus subpáginas.
        $padre = $this->entrada(['etiqueta' => 'Cursos', 'route_name' => 'cursos.index']);
        MenuItem::create([
            'parent_id' => $padre->id, 'etiqueta' => 'Admisión',
            'route_name' => 'cursos.admision',
            'orden' => 0, 'is_visible' => true,
        ]);

        $menu = $this->getJson('/api/v1/menu')->assertOk()->json('data');

        $this->assertSame('/cursos', $menu[0]['enlace']);
        $this->assertSame('/cursos/admision', $menu[0]['hijos'][0]['enlace']);
    }

    public function test_una_entrada_sin_hijos_llega_como_enlace_simple(): void
    {
        $this->entrada(['etiqueta' => 'Talleres', 'route_name' => 'talleres.index']);

        $menu = $this->getJson('/api/v1/menu')->assertOk()->json('data');

        $this->assertSame('/talleres', $menu[0]['enlace']);
        $this->assertSame([], $menu[0]['hijos']);
    }

    public function test_un_enlace_caducado_se_retira_del_sitio_solo(): void
    {
        // El caso real: «Criterios de Evaluación» apuntó al documento de 2025
        // durante un año sin que nadie lo notara.
        $this->entrada([
            'etiqueta' => 'Criterios de Evaluación 2025',
            'route_name' => null,
            'url' => 'https://posgrado.unmsm.edu.pe/doc/criterios-2025',
            'vigente_hasta' => now()->subMonths(8),
        ]);

        $this->getJson('/api/v1/menu')->assertOk()
            ->assertJsonMissing(['etiqueta' => 'Criterios de Evaluación 2025']);
    }

    public function test_el_ultimo_dia_de_vigencia_el_enlace_sigue_activo(): void
    {
        $this->entrada(['etiqueta' => 'Vence hoy', 'vigente_hasta' => now()]);

        $this->getJson('/api/v1/menu')->assertOk()
            ->assertJsonFragment(['etiqueta' => 'Vence hoy']);
    }

    public function test_sin_fecha_de_retirada_el_enlace_no_caduca(): void
    {
        $this->entrada(['etiqueta' => 'Permanente']);

        $this->getJson('/api/v1/menu')->assertOk()
            ->assertJsonFragment(['etiqueta' => 'Permanente']);
    }

    public function test_una_subentrada_caducada_se_retira_sin_tocar_a_sus_hermanas(): void
    {
        $padre = $this->entrada(['etiqueta' => 'Admisión', 'route_name' => null]);
        MenuItem::create([
            'parent_id' => $padre->id, 'etiqueta' => 'Vacantes vigentes',
            'url' => 'https://ejemplo.pe/v', 'orden' => 0, 'is_visible' => true,
        ]);
        MenuItem::create([
            'parent_id' => $padre->id, 'etiqueta' => 'Vacantes del año pasado',
            'url' => 'https://ejemplo.pe/viejo', 'orden' => 1, 'is_visible' => true,
            'vigente_hasta' => now()->subYear(),
        ]);

        $this->getJson('/api/v1/menu')->assertOk()
            ->assertJsonFragment(['etiqueta' => 'Vacantes vigentes'])
            ->assertJsonMissing(['etiqueta' => 'Vacantes del año pasado']);
    }

    /**
     * Lo caducado tiene que seguir viendose en el panel.
     *
     * El sitio lo esconde —para eso esta la fecha—, pero si el panel aplicara
     * el mismo criterio la entrada desapareceria de la lista el dia que caduca
     * y no habria forma de renovarla ni de borrarla: quedaria en la base para
     * siempre, invisible desde las dos puntas.
     */
    public function test_el_panel_si_muestra_lo_caducado_para_poder_arreglarlo(): void
    {
        $item = $this->entrada([
            'etiqueta' => 'Criterios 2025',
            'vigente_hasta' => now()->subMonths(8),
        ]);

        // Fuera del sitio...
        $this->getJson('/api/v1/menu')->assertOk()->assertJsonMissing(['etiqueta' => 'Criterios 2025']);

        // ...pero presente en el panel.
        Livewire::test(ListMenuItems::class)->assertCanSeeTableRecords([$item]);
    }

    public function test_la_fecha_de_retirada_se_guarda_desde_el_panel(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'etiqueta' => 'Convocatoria',
                'route_name' => 'nosotros',
                'is_visible' => true,
                'vigente_hasta' => '2026-12-31',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = MenuItem::where('etiqueta', 'Convocatoria')->firstOrFail();
        $this->assertSame('2026-12-31', $item->vigente_hasta->format('Y-m-d'));
    }
}
