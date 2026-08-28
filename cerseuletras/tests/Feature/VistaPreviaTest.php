<?php

namespace Tests\Feature;

use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vista previa de borradores.
 *
 * Lo que se prueba aquí no es que la vista previa se vea bonita: es que un
 * borrador **no se escape**. La función existe para enseñar contenido sin
 * publicar, así que cada camino que lo entrega tiene que exigir credencial, y
 * el que no la trae tiene que recibir exactamente lo mismo que un desconocido
 * pidiendo algo que no existe.
 */
class VistaPreviaTest extends TestCase
{
    use RefreshDatabase;

    /** Sin factory: el modelo no tiene, y el resto de pruebas también crea a mano. */
    private function borrador(): Programa
    {
        return Programa::create([
            'grado' => 'Curso',
            'nombre' => 'Borrador sin publicar',
            'slug' => 'borrador-sin-publicar',
            'modalidad' => 'Virtual',
            'duracion' => 2,
            'creditos' => 24,
            'estado' => Programa::ESTADO_BORRADOR,
        ]);
    }

    public function test_la_api_no_entrega_un_borrador_sin_token(): void
    {
        $this->borrador();
        config(['sitio.vista_previa.token' => 'el-token-bueno']);

        $this->getJson('/api/v1/programas/borrador-sin-publicar')->assertNotFound();

        $listado = $this->getJson('/api/v1/programas')->json('data');
        $this->assertNotContains('borrador-sin-publicar', array_column($listado, 'slug'));
    }

    public function test_la_api_entrega_el_borrador_con_el_token(): void
    {
        $this->borrador();
        config(['sitio.vista_previa.token' => 'el-token-bueno']);

        $this->withHeader('X-Vista-Previa', 'el-token-bueno')
            ->getJson('/api/v1/programas/borrador-sin-publicar')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Borrador sin publicar');
    }

    public function test_un_token_equivocado_no_abre_nada(): void
    {
        $this->borrador();
        config(['sitio.vista_previa.token' => 'el-token-bueno']);

        $this->withHeader('X-Vista-Previa', 'el-token-malo')
            ->getJson('/api/v1/programas/borrador-sin-publicar')
            ->assertNotFound();
    }

    /**
     * La guarda que más importa: una instalación recién hecha trae el token
     * vacío. Sin esta comprobación, mandar la cabecera vacía —o cualquier
     * cabecera, si la comparación fuera laxa— abriría todos los borradores.
     */
    public function test_sin_token_configurado_la_vista_previa_esta_cerrada(): void
    {
        $this->borrador();
        config(['sitio.vista_previa.token' => '']);

        $this->withHeader('X-Vista-Previa', '')
            ->getJson('/api/v1/programas/borrador-sin-publicar')
            ->assertNotFound();

        $this->withHeader('X-Vista-Previa', 'lo-que-sea')
            ->getJson('/api/v1/programas/borrador-sin-publicar')
            ->assertNotFound();
    }

    /**
     * Una respuesta con borradores no puede quedarse en ninguna caché: si un
     * intermediario la guarda, acaba sirviéndosela a quien no traía token.
     */
    public function test_la_respuesta_con_borradores_no_se_cachea(): void
    {
        $this->borrador();
        config(['sitio.vista_previa.token' => 'el-token-bueno']);

        $this->withHeader('X-Vista-Previa', 'el-token-bueno')
            ->getJson('/api/v1/programas/borrador-sin-publicar')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_las_rutas_de_vista_previa_exigen_sesion(): void
    {
        $programa = $this->borrador();

        $this->get("/admin/vista-previa/programas/{$programa->id}")->assertRedirect('/login');
        $this->get('/admin/vista-previa/estado')->assertRedirect('/login');
        $this->get('/admin/vista-previa/sitio/cursos/borrador-sin-publicar/')->assertRedirect('/login');
    }

    /**
     * Sin configurar, la pantalla lo dice en vez de fallar de forma rara o
     * quedarse esperando un servicio que nunca va a contestar.
     */
    public function test_sin_configurar_responde_503_a_quien_ha_entrado(): void
    {
        $programa = $this->borrador();
        config(['sitio.vista_previa.token' => '', 'sitio.reconstruccion.token' => '']);

        // Con rol de admin: el grupo lleva `isAdmin`, y sin el rol la
        // peticion se va en un 302 antes de llegar al controlador.
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get("/admin/vista-previa/programas/{$programa->id}")
            ->assertStatus(503);
    }
}
