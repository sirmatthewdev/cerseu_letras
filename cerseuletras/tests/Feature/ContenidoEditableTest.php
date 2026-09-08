<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Contenido editable de /tramites y /admision.
 */
class ContenidoEditableTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function paginaConSeccion(string $slug, array $extra = []): ContentPage
    {
        $pagina = ContentPage::create(['slug' => $slug, 'titulo' => ucfirst($slug)]);
        $pagina->secciones()->create($extra + [
            'titulo' => 'Sección de prueba',
            'cuerpo' => '<p>Texto original</p>',
            'orden' => 0,
            'is_visible' => true,
        ]);

        ContentPage::clearCache($slug);

        return $pagina;
    }

    public function test_las_secciones_se_muestran_en_la_pagina_publica(): void
    {
        $this->paginaConSeccion('tramites', ['grupo' => 'maestria']);

        // /tramites conserva su marcado original: aquí se comprueba el modelo,
        // que es lo que la página consumirá cuando se conecte.
        $secciones = ContentPage::porSlug('tramites')->seccionesDe('maestria');
        $this->assertCount(1, $secciones);
        $this->assertSame('Sección de prueba', $secciones[0]->titulo);
    }

    public function test_una_seccion_oculta_no_se_muestra(): void
    {
        $pagina = $this->paginaConSeccion('tramites', ['grupo' => 'maestria']);
        $pagina->secciones()->update(['is_visible' => false]);
        ContentPage::clearCache('tramites');

        $this->assertCount(0, ContentPage::porSlug('tramites')->seccionesDe('maestria'));
    }

    /** Los datos de contacto siguen saliendo de Configuración, no del texto. */
    public function test_los_tokens_de_contacto_se_resuelven(): void
    {
        // `site_settings` es un registro único que la migración ya siembra:
        // se actualiza el existente en lugar de crear un segundo, que
        // `SiteSetting::get()` (basado en `first()`) nunca leería.
        SiteSetting::firstOrFail()->update(['email_tramites' => 'tramites@ejemplo.pe', 'telefono' => '900 111 222']);
        SiteSetting::clearCache();

        $pagina = $this->paginaConSeccion('tramites', ['grupo' => 'maestria']);
        $pagina->secciones()->update([
            'cuerpo' => '<p>Escribe a {{email_tramites}} o llama al {{telefono}}</p>',
        ]);
        ContentPage::clearCache('tramites');

        $render = ContentPage::porSlug('tramites')->seccionesDe('maestria')[0]->cuerpo_renderizado;
        $this->assertStringContainsString('tramites@ejemplo.pe', $render);
        $this->assertStringContainsString('900 111 222', $render);
        $this->assertStringNotContainsString('{{email_tramites}}', $render);
    }

    public function test_el_panel_guarda_titulo_orden_y_visibilidad(): void
    {
        $pagina = $this->paginaConSeccion('admision');

        Livewire::test(EditContentPage::class, ['record' => $pagina->getRouteKey()])
            ->fillForm([
                'titulo' => 'Proceso de Admisión 2027-I',
                'subtitulo' => 'Nuevo subtítulo',
                'secciones' => [
                    ['titulo' => 'Paso nuevo', 'cuerpo' => '<p>Contenido nuevo</p>', 'is_visible' => true],
                    ['titulo' => 'Paso posterior', 'cuerpo' => '<p>Otro</p>', 'is_visible' => true],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $pagina->refresh();
        $this->assertSame('Proceso de Admisión 2027-I', $pagina->titulo);

        $secciones = $pagina->secciones()->get();
        $this->assertCount(2, $secciones);
        $this->assertSame('Paso nuevo', $secciones[0]->titulo);

        // Lo que se guarda es el orden RELATIVO, que es de lo que tira la
        // página. El numero de partida no se fija a proposito: el repetidor de
        // Filament empieza en 1 y el formulario anterior en 0, y clavar el
        // valor absoluto solo ataria la prueba al panel de turno.
        $this->assertLessThan($secciones[1]->orden, $secciones[0]->orden);

        $this->assertSame('Paso nuevo', ContentPage::porSlug('admision')->seccionesDe()[0]->titulo);
    }

    /**
     * Una sección sin título se pinta en el sitio como un bloque de texto
     * suelto: sin encabezado, y sin ancla a la que enlazar desde el índice.
     */
    public function test_una_seccion_sin_titulo_se_rechaza(): void
    {
        $pagina = $this->paginaConSeccion('admision');

        Livewire::test(EditContentPage::class, ['record' => $pagina->getRouteKey()])
            ->fillForm([
                'secciones' => [['titulo' => '', 'cuerpo' => '<p>x</p>']],
            ])
            ->call('save')
            ->assertHasFormErrors();
    }
}
