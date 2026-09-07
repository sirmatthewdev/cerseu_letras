<?php

namespace Tests\Feature;

use App\Filament\Pages\CronogramaDeAdmision;
use App\Models\CronogramaAdmision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El bloque «Cómo inscribirte» de la portada.
 *
 * Era el último recurso del panel sin migrar. Lo que se comprueba aquí no es
 * que la pantalla abra, sino que el viaje completo funcione: escribir, guardar,
 * y que el sitio lo reciba — que son tres sitios distintos donde puede
 * romperse, y el tercero es el único que ve el visitante.
 */
class CronogramaDeAdmisionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_la_pagina_exige_ser_administrador(): void
    {
        $this->get('/panel/cronograma-de-admision')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get('/panel/cronograma-de-admision')
            ->assertForbidden();
    }

    public function test_guarda_el_encabezado_y_los_pasos(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CronogramaDeAdmision::class)
            ->fillForm([
                'eyebrow' => 'Formación abierta',
                'titulo' => 'Cómo inscribirte',
                'boton_texto' => 'Ver la oferta',
                'boton_url' => '/cursos',
                'is_visible' => true,
                'pasos' => [
                    [
                        'titulo' => 'Elige tu programa',
                        'fecha_inicio' => '3 de marzo',
                        'fecha_fin' => '14 de marzo',
                        'detalle' => 'Revisa la oferta vigente.',
                        'publico' => 'Público general',
                        'icono' => 'birrete',
                        'destacado' => true,
                        'is_visible' => true,
                    ],
                    [
                        'titulo' => 'Envía tu solicitud',
                        'fecha_inicio' => 'Marzo 2026',
                        'icono' => 'correo',
                        'is_visible' => true,
                    ],
                ],
            ])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $bloque = CronogramaAdmision::query()->with('pasos')->first();

        $this->assertSame('Cómo inscribirte', $bloque->titulo);
        $this->assertCount(2, $bloque->pasos);

        // El orden sale de la posición en el repetidor, no de un campo que
        // haya que rellenar a mano.
        $this->assertSame('Elige tu programa', $bloque->pasos->firstWhere('orden', 0)->titulo);
        $this->assertSame('Envía tu solicitud', $bloque->pasos->firstWhere('orden', 1)->titulo);
    }

    /**
     * Y que llegue al sitio.
     *
     * Dos comprobaciones separadas a proposito, porque la primera version las
     * juntaba y no probaba lo que yo creia: pedir la API antes y despues no
     * detectaba nada al quitar `clearCache()`, asi que pasaba por un motivo
     * que no era la cache. Aqui la cache se mira de frente.
     */
    public function test_lo_guardado_llega_a_la_api(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CronogramaDeAdmision::class)
            ->fillForm([
                'titulo' => 'Titular recién escrito',
                'is_visible' => true,
                'pasos' => [
                    ['titulo' => 'Paso único', 'icono' => 'check', 'is_visible' => true],
                ],
            ])
            ->call('guardar');

        $inscripcion = $this->getJson('/api/v1/sitio')->assertOk()->json('data.inscripcion');

        $this->assertSame('Titular recién escrito', $inscripcion['titulo']);
        $this->assertSame('Paso único', $inscripcion['pasos'][0]['titulo']);
    }

    /**
     * Guardar tiene que olvidar la cache.
     *
     * El bloque se sirve cacheado una hora. Sin olvidarla, la portada sigue
     * mostrando lo anterior y quien edita concluye que no se ha guardado — un
     * fallo que solo se nota en produccion, donde la cache es Redis y sobrevive
     * a la peticion.
     *
     * Hace falta sembrar una fila antes de calentar. `Cache::remember` guarda el
     * nulo, pero `get()` devuelve nulo igual, asi que un bloque inexistente NO
     * llega a quedar cacheado de hecho: sin fila, esta prueba pasaria con o sin
     * `clearCache()` y no guardaria nada. Se descubrio comprobandolo.
     */
    public function test_guardar_olvida_la_cache(): void
    {
        $bloque = CronogramaAdmision::query()->create(['titulo' => 'Lo de antes', 'is_visible' => true]);
        $bloque->pasos()->create(['titulo' => 'Paso viejo', 'icono' => 'check', 'orden' => 0, 'is_visible' => true]);

        // Se calienta como la calienta la primera visita a la portada.
        CronogramaAdmision::get();
        $this->assertNotNull(Cache::get('cronograma_admision'));

        $this->actingAs($this->admin());

        Livewire::test(CronogramaDeAdmision::class)
            ->fillForm([
                'titulo' => 'Otro titular',
                'is_visible' => true,
                'pasos' => [['titulo' => 'Paso', 'icono' => 'check', 'is_visible' => true]],
            ])
            ->call('guardar');

        $this->assertNull(
            Cache::get('cronograma_admision'),
            'Guardar no olvido la cache: la portada seguiria mostrando lo anterior.'
        );
    }

    /**
     * Las fechas son texto y se unen con un guion. No es un capricho: la Unidad
     * publica «Del 3 al 14 de marzo» o «Marzo 2026», y un selector de calendario
     * obligaría a inventar un día concreto donde no lo hay.
     */
    public function test_las_dos_fechas_se_unen_con_un_guion(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CronogramaDeAdmision::class)
            ->fillForm([
                'is_visible' => true,
                'pasos' => [[
                    'titulo' => 'Inscripción',
                    'fecha_inicio' => '3 de marzo',
                    'fecha_fin' => '14 de marzo',
                    'icono' => 'calendario',
                    'is_visible' => true,
                ]],
            ])
            ->call('guardar');

        $inscripcion = $this->getJson('/api/v1/sitio')->json('data.inscripcion');

        $this->assertSame('3 de marzo - 14 de marzo', $inscripcion['pasos'][0]['fecha']);
    }

    /** Sin pasos visibles no se publica la sección, en vez de un título sobre un hueco. */
    public function test_sin_pasos_visibles_la_seccion_no_se_publica(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CronogramaDeAdmision::class)
            ->fillForm([
                'titulo' => 'Con título pero sin pasos',
                'is_visible' => true,
                'pasos' => [],
            ])
            ->call('guardar');

        $this->getJson('/api/v1/sitio')->assertJsonPath('data.inscripcion', null);
    }
}
