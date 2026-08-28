<?php

namespace Tests\Feature;

use App\Models\Programa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La forma de los campos largos y de la inversión.
 *
 * Estos campos no los valida nadie al guardar: son columnas JSON, y cualquier
 * estructura cabe. Lo que decide si funcionan está en el otro extremo —los
 * accesores del modelo y `ProgramaResource`, que buscan claves por nombre—, así
 * que un cambio de forma no rompe al guardar sino después, en silencio, cuando
 * la ficha del sitio no encuentra lo que busca.
 *
 * De ahí estas pruebas: fijan el contrato entre el formulario del panel y lo
 * que el sitio espera recibir.
 */
class ContenidoLargoProgramaTest extends TestCase
{
    use RefreshDatabase;

    private function programa(array $atributos = []): Programa
    {
        return Programa::create(array_merge([
            'grado' => 'Curso',
            'nombre' => 'Curso de prueba',
            'slug' => 'curso-de-prueba',
            'modalidad' => 'Virtual',
            'estado' => Programa::ESTADO_PUBLICADO,
        ], $atributos));
    }

    /**
     * Los bloques largos son listas, no HTML.
     *
     * El sitio los declaraba `string` y los pintaba con `set:html`, así que el
     * día que alguien rellenara uno la página habría mostrado «[object
     * Object]». No saltaba porque los 39 programas los tienen vacíos.
     */
    public function test_los_bloques_largos_viajan_como_listas(): void
    {
        $this->programa([
            'objetivos_academicos' => ['Redactar con precisión', 'Argumentar'],
            'perfil_ingresante' => ['Interés por las humanidades'],
        ]);

        $datos = $this->getJson('/api/v1/programas/curso-de-prueba')->assertOk()->json('data');

        $this->assertIsArray($datos['objetivos']);
        $this->assertSame(['Redactar con precisión', 'Argumentar'], $datos['objetivos']);
        $this->assertIsArray($datos['perfil_ingresante']);
    }

    /**
     * La estructura de inversión que escribe el formulario tiene que ser la que
     * leen los accesores. Las claves están puestas a mano en los dos sitios.
     */
    public function test_la_inversion_que_guarda_el_panel_es_la_que_lee_la_api(): void
    {
        $this->programa([
            'inversion_economica' => [
                'costo_total' => 900,
                'costo_matricula' => 100,
                'derecho_inscripcion' => ['bachiller_unmsm' => 50, 'otras_universidades' => 80],
                'modalidades' => [[
                    'nombre' => 'Pago fraccionado',
                    'cuotas' => [
                        ['etiqueta' => 'Primera cuota', 'monto' => 250, 'fecha' => '30 de abril'],
                        ['etiqueta' => 'Segunda cuota', 'monto' => 250, 'fecha' => '30 de mayo'],
                    ],
                ]],
                'condiciones' => [['texto' => 'El pago se hace en la caja de la Facultad.']],
            ],
        ]);

        $inversion = $this->getJson('/api/v1/programas/curso-de-prueba')
            ->assertOk()
            ->json('data.inversion');

        // `assertEquals` y no `assertSame` en los importes: la API los emite
        // como float, pero JSON no distingue entero de decimal, asi que 900.0
        // viaja como `900` y vuelve convertido en int. Comparar el tipo aqui
        // seria comprobar una casualidad del formato, no el contrato.
        $this->assertEquals(900, $inversion['costo_total']);
        $this->assertEquals(100, $inversion['costo_matricula']);
        $this->assertEquals(50, $inversion['derecho_inscripcion']['bachiller_unmsm']);

        $this->assertCount(1, $inversion['modalidades']);
        $this->assertSame('Pago fraccionado', $inversion['modalidades'][0]['nombre']);
        $this->assertCount(2, $inversion['modalidades'][0]['cuotas']);
        $this->assertSame('Primera cuota', $inversion['modalidades'][0]['cuotas'][0]['etiqueta']);
        $this->assertEquals(250, $inversion['modalidades'][0]['cuotas'][0]['monto']);

        // Las condiciones se guardan como objetos con `texto` y salen como
        // cadenas: el accesor las aplana, y la ficha las pinta directamente.
        $this->assertSame(['El pago se hace en la caja de la Facultad.'], $inversion['condiciones']);
    }

    /**
     * Sin nada cargado, el bloque no sale: la ficha no debe enseñar una sección
     * de inversión vacía.
     */
    public function test_sin_inversion_no_se_manda_bloque(): void
    {
        $this->programa();

        $this->getJson('/api/v1/programas/curso-de-prueba')
            ->assertOk()
            ->assertJsonPath('data.inversion', null);
    }
}
