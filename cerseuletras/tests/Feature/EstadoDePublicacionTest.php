<?php

namespace Tests\Feature;

use App\Filament\Widgets\PublicacionDelSitio;
use App\Jobs\ReconstruirSitio;
use App\Models\User;
use App\Support\EstadoDePublicacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El panel dice si lo guardado ya se ve en el sitio.
 *
 * Antes, si la cola estaba parada o el build fallaba, el panel seguía diciendo
 * «guardado» y el sitio se quedaba con la versión anterior sin que nadie se
 * enterase hasta que alguien de fuera lo reportaba.
 */
class EstadoDePublicacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sitio.reconstruccion.url' => 'http://build:4322/reconstruir',
            'sitio.reconstruccion.token' => 'un-token',
        ]);
    }

    private function build(bool $construyendo, string $estado, ?string $salida = null): void
    {
        Http::fake(['build:4322/estado' => Http::response([
            'construyendo' => $construyendo,
            'ultimo' => ['estado' => $estado, 'terminado' => '2026-10-22T16:02:55.482Z', 'duracionMs' => 13000, 'salida' => $salida],
        ])]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_un_build_correcto_dice_que_el_sitio_esta_al_dia(): void
    {
        $this->build(false, 'correcto');

        $this->assertSame(EstadoDePublicacion::PUBLICADO, EstadoDePublicacion::actual()['estado']);
        Http::assertSent(fn ($p) => $p->url() === 'http://build:4322/estado');
    }

    public function test_un_build_fallido_trae_la_salida_para_saber_por_que(): void
    {
        $this->build(false, 'fallido', 'Error: la API respondió 500');

        $estado = EstadoDePublicacion::actual();

        $this->assertSame(EstadoDePublicacion::FALLIDO, $estado['estado']);
        $this->assertStringContainsString('respondió 500', $estado['detalle']);
        $this->assertTrue(EstadoDePublicacion::hayProblema($estado));
    }

    public function test_mientras_construye_lo_dice(): void
    {
        $this->build(true, 'correcto');

        $this->assertSame(EstadoDePublicacion::PUBLICANDO, EstadoDePublicacion::actual()['estado']);
    }

    public function test_un_cambio_recien_guardado_queda_pendiente(): void
    {
        $this->build(false, 'correcto');
        EstadoDePublicacion::marcarPendiente();

        $this->assertSame(EstadoDePublicacion::PENDIENTE, EstadoDePublicacion::actual()['estado']);
    }

    public function test_un_cambio_que_lleva_mucho_sin_publicarse_es_un_atasco(): void
    {
        // Lo que pasa con la cola parada: se guarda, se encola, y nadie lo recoge.
        $this->build(false, 'correcto');
        EstadoDePublicacion::marcarPendiente();
        $this->travel(11)->minutes();

        $this->assertSame(EstadoDePublicacion::ATASCADO, EstadoDePublicacion::actual()['estado']);
    }

    public function test_entregar_el_encargo_quita_el_pendiente(): void
    {
        Http::fake([
            'build:4322/reconstruir' => Http::response(['mensaje' => 'ok'], 202),
            'build:4322/estado' => Http::response(['construyendo' => false, 'ultimo' => ['estado' => 'correcto', 'terminado' => null]]),
        ]);
        EstadoDePublicacion::marcarPendiente();

        (new ReconstruirSitio())->handle();

        $this->assertSame(EstadoDePublicacion::PUBLICADO, EstadoDePublicacion::actual()['estado']);
    }

    public function test_agotar_los_reintentos_deja_el_fallo_a_la_vista(): void
    {
        $this->build(false, 'correcto');

        (new ReconstruirSitio())->failed(new \RuntimeException('El servicio de build respondió 503'));

        $estado = EstadoDePublicacion::actual();
        $this->assertSame(EstadoDePublicacion::FALLIDO, $estado['estado']);
        $this->assertStringContainsString('503', $estado['detalle']);
    }

    public function test_sin_servicio_de_build_no_se_da_por_publicado(): void
    {
        Http::fake(['build:4322/estado' => Http::response(null, 500)]);

        $this->assertSame(EstadoDePublicacion::SIN_CONEXION, EstadoDePublicacion::actual()['estado']);
    }

    public function test_sin_url_no_hay_nada_que_vigilar(): void
    {
        Http::fake();
        config(['sitio.reconstruccion.url' => '']);

        $this->assertNull(EstadoDePublicacion::actual());
        Http::assertNothingSent();
    }

    public function test_el_escritorio_lo_muestra_y_deja_volver_a_publicar(): void
    {
        Queue::fake();
        $this->build(false, 'fallido', 'astro build: algo se rompió');
        $this->actingAs($this->admin());

        Livewire::test(PublicacionDelSitio::class)
            ->assertSee('La última publicación falló')
            ->assertSee('algo se rompió')
            ->call('volverAPublicar');

        Queue::assertPushed(ReconstruirSitio::class);
    }

    public function test_un_fallo_se_avisa_tambien_fuera_del_escritorio(): void
    {
        // Quien edita un taller no pasa por el escritorio.
        $this->build(false, 'fallido', 'astro build: algo se rompió');

        $this->actingAs($this->admin())
            ->get('/panel/programas')
            ->assertOk()
            ->assertSee('La última publicación falló')
            ->assertSee('Ver detalle');
    }

    public function test_con_todo_bien_no_se_molesta_fuera_del_escritorio(): void
    {
        $this->build(false, 'correcto');

        $this->actingAs($this->admin())
            ->get('/panel/programas')
            ->assertOk()
            ->assertDontSee('Ver detalle');
    }
}
