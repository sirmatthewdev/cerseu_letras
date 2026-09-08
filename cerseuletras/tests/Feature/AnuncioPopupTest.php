<?php

namespace Tests\Feature;

use App\Models\Anuncio;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Popup de anuncios de la portada.
 *
 * Se parte en dos: lo que el panel guarda —que sigue igual— y lo que llega al
 * sitio, que ahora viaja por /api/v1/anuncios en lugar de pintarse dentro del
 * HTML de Blade. Las reglas de vigencia no cambian.
 *
 * Lo que era marcado —la proporción del marco, que la imagen llene sin bandas,
 * que el popup no se cuele fuera de la portada— se comprueba ahora contra el
 * sitio, en sitio/e2e: es donde vive ese componente.
 */
class AnuncioPopupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function anuncio(array $extra = []): Anuncio
    {
        Anuncio::clearCache();

        return Anuncio::create($extra + [
            'titulo' => 'Convocatoria',
            'imagen' => 'https://ejemplo.pe/anuncio.jpg',
            'alt' => 'Convocatoria abierta',
            'orden' => 0,
            'is_visible' => true,
        ]);
    }

    public function test_sin_anuncios_vigentes_la_lista_llega_vacia(): void
    {
        // Con la lista vacía el sitio no pinta nada: ni marcado, ni CSS, ni
        // JS. Que el popup viva solo en la portada lo comprueba sitio/e2e.
        $this->getJson('/api/v1/anuncios')->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_un_anuncio_caducado_no_se_muestra(): void
    {
        $this->anuncio(['alt' => 'Ya pasó', 'visible_hasta' => now()->subDay()]);

        $this->getJson('/api/v1/anuncios')->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_un_anuncio_programado_todavia_no_se_muestra(): void
    {
        $this->anuncio(['alt' => 'Aún no toca', 'visible_desde' => now()->addWeek()]);

        $this->getJson('/api/v1/anuncios')->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_el_ultimo_dia_de_vigencia_todavia_cuenta(): void
    {
        // La comparación es por día, no por instante: si caduca hoy, hoy se ve.
        $this->anuncio(['alt' => 'Último día', 'visible_hasta' => now()]);

        $this->getJson('/api/v1/anuncios')->assertOk()
            ->assertJsonFragment(['alt' => 'Último día']);
    }

    public function test_un_anuncio_sin_fechas_se_muestra_siempre(): void
    {
        $this->anuncio(['alt' => 'Permanente']);

        $this->getJson('/api/v1/anuncios')->assertOk()
            ->assertJsonFragment(['alt' => 'Permanente']);
    }

    public function test_un_anuncio_oculto_no_se_muestra_aunque_esté_en_fecha(): void
    {
        $this->anuncio(['alt' => 'Apagado', 'is_visible' => false]);

        $this->getJson('/api/v1/anuncios')->assertOk()->assertJsonPath('data.items', []);
    }

    /*
     * Aqui vivian siete pruebas que entraban por `POST /admin/anuncios`. No se
     * han perdido: estan en `PanelAnunciosTest`, contra Filament, que es el
     * unico panel que queda. En este fichero se queda lo que no depende de
     * ningun panel — la vigencia: que anuncio se muestra y cuando.
     */

    public function test_el_retardo_configurado_llega_a_la_portada(): void
    {
        $this->anuncio();
        $ajustes = SiteSetting::first() ?: SiteSetting::create(['site_name' => 'Posgrado']);
        $ajustes->update(['popup_retardo_ms' => 4500]);
        SiteSetting::clearCache();

        // El retardo viaja junto a los anuncios: separarlos obligaría al
        // sitio a decidirlo por su cuenta y dejaría de ser administrable.
        $this->getJson('/api/v1/anuncios')->assertOk()
            ->assertJsonPath('data.ajustes.retardo', 4500);
    }

    public function test_calcula_cuanto_se_recortara_de_cada_imagen(): void
    {
        // Marco 4:5. Una imagen más ancha pierde por los lados; una más alta,
        // por arriba y por abajo.
        $exacta = $this->anuncio(['imagen_ancho' => 1000, 'imagen_alto' => 1250]);
        $this->assertSame(0, $exacta->recorte_porcentaje);
        $this->assertFalse($exacta->recorte_notable);
        $this->assertNull($exacta->recorte_lado);

        $ancha = $this->anuncio(['imagen_ancho' => 1200, 'imagen_alto' => 900]);
        $this->assertSame(40, $ancha->recorte_porcentaje);
        $this->assertSame('por los lados', $ancha->recorte_lado);

        $alta = $this->anuncio(['imagen_ancho' => 900, 'imagen_alto' => 1600]);
        $this->assertSame(30, $alta->recorte_porcentaje);
        $this->assertSame('por arriba y por abajo', $alta->recorte_lado);
    }

    public function test_sin_medidas_conocidas_no_inventa_un_recorte(): void
    {
        $anuncio = $this->anuncio(['imagen_ancho' => null, 'imagen_alto' => null]);

        $this->assertNull($anuncio->recorte_porcentaje);
        $this->assertFalse($anuncio->recorte_notable);
    }
}
