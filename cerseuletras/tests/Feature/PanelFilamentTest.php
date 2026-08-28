<?php

namespace Tests\Feature;

use App\Models\Docente;
use App\Models\User;
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
}
