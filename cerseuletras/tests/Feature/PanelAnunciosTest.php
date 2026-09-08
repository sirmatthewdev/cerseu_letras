<?php

namespace Tests\Feature;

use App\Filament\Pages\ConfiguracionDelSitio;
use App\Filament\Resources\Anuncios\Pages\CreateAnuncio;
use App\Filament\Resources\Anuncios\Pages\EditAnuncio;
use App\Filament\Resources\Anuncios\Pages\ListAnuncios;
use App\Models\Anuncio;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El anuncio de la portada, desde el panel.
 *
 * Estas comprobaciones venían de `AnuncioPopupTest` y entraban por
 * `POST /admin/anuncios`. Al retirar el panel Blade no se borraron: las reglas
 * que guardaban —que el archivo sea una imagen, que las fechas no vayan al
 * revés, que editar sin volver a subir no borre la imagen, que las medidas
 * reales lleguen a la portada— siguen siendo ciertas, solo que ahora la puerta
 * es Filament. Perderlas al cambiar de panel habría sido cambiar de panel
 * *y* de garantías a la vez, sin decirlo.
 *
 * Lo que no se toca aquí es la vigencia (qué anuncio se muestra y cuándo): eso
 * es del modelo y sigue en `AnuncioPopupTest`.
 */
class PanelAnunciosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    public function test_crea_un_anuncio_con_imagen_y_guarda_sus_medidas(): void
    {
        Storage::fake('public');

        Livewire::test(CreateAnuncio::class)
            ->fillForm([
                'titulo' => 'Convocatoria 2026-I',
                'alt' => 'Convocatoria 2026-I abierta',
                'imagen' => [UploadedFile::fake()->image('anuncio.jpg', 900, 1273)],
                'is_visible' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $anuncio = Anuncio::firstOrFail();
        $this->assertSame('Convocatoria 2026-I', $anuncio->titulo);
        Storage::disk('public')->assertExists($anuncio->imagen);

        // Las medidas no son decorativas: la portada reserva con ellas el hueco
        // exacto antes de que cargue la imagen. Sin ellas daba un salto de
        // 260 px al cargar.
        $this->assertSame(900, $anuncio->imagen_ancho);
        $this->assertSame(1273, $anuncio->imagen_alto);

        Anuncio::clearCache();
        $this->getJson('/api/v1/anuncios')->assertOk()
            ->assertJsonFragment(['ancho' => 900, 'alto' => 1273]);
    }

    public function test_se_rechaza_un_archivo_que_no_es_imagen(): void
    {
        Storage::fake('public');

        Livewire::test(CreateAnuncio::class)
            ->fillForm([
                'titulo' => 'Malicioso',
                'imagen' => [UploadedFile::fake()->create('script.php', 10, 'application/x-php')],
            ])
            ->call('create')
            ->assertHasFormErrors(['imagen']);

        $this->assertSame(0, Anuncio::count());
    }

    public function test_la_fecha_de_fin_no_puede_ser_anterior_a_la_de_inicio(): void
    {
        Storage::fake('public');

        Livewire::test(CreateAnuncio::class)
            ->fillForm([
                'titulo' => 'Fechas al revés',
                'imagen' => [UploadedFile::fake()->image('a.jpg')],
                'visible_desde' => '2026-06-01',
                'visible_hasta' => '2026-05-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['visible_hasta']);
    }

    /**
     * El campo de imagen viene relleno al editar. Si al guardar sin tocarlo se
     * enviara vacío, el anuncio se quedaría sin imagen y en la portada saldría
     * un hueco — y nadie relaciona «cambié el título» con «desapareció la
     * imagen».
     */
    public function test_editar_sin_subir_imagen_conserva_la_que_habia(): void
    {
        Storage::fake('public');
        // El fichero tiene que existir de verdad: Filament comprueba el disco al
        // hidratar el campo y descarta las rutas que no encuentra, asi que sin
        // esto el formulario abriria ya vacio y no se estaria probando nada.
        Storage::disk('public')->put('anuncios/original.jpg', 'x');

        $anuncio = Anuncio::create([
            'titulo' => 'Convocatoria',
            'imagen' => 'anuncios/original.jpg',
            'alt' => 'Convocatoria abierta',
            'orden' => 0,
            'is_visible' => true,
        ]);

        Livewire::test(EditAnuncio::class, ['record' => $anuncio->getRouteKey()])
            ->fillForm(['titulo' => 'Nombre cambiado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $anuncio->refresh();
        $this->assertSame('Nombre cambiado', $anuncio->titulo);
        $this->assertSame('anuncios/original.jpg', $anuncio->imagen);
    }

    /**
     * Auto-avance es un interruptor, no una duración.
     *
     * La columna es `tinyint(1)` y la portada lo lee como booleano: rota cada
     * 4 segundos fijos. El formulario lo pintaba como «Auto-avance (ms)», así
     * que quien escribiera 3000 creía estar fijando el ritmo —no lo fijaba— y
     * además ese número no cabe en la columna.
     */
    public function test_el_auto_avance_se_guarda_como_interruptor(): void
    {
        SiteSetting::first() ?: SiteSetting::create(['site_name' => 'CERSEU']);

        Livewire::test(ConfiguracionDelSitio::class)
            ->fillForm(['popup_auto_avance' => true, 'popup_frecuencia' => 'dia'])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $ajustes = SiteSetting::first();
        $this->assertTrue((bool) $ajustes->popup_auto_avance);
        $this->assertSame('dia', $ajustes->popup_frecuencia);

        $this->getJson('/api/v1/anuncios')->assertOk()
            ->assertJsonPath('data.ajustes.autoAvance', true);
    }

    /**
     * Pasados unos veinte segundos ya no queda nadie esperando: un retardo
     * mayor apaga el anuncio de hecho, sin que se vea que está apagado.
     */
    public function test_un_retardo_desmedido_se_rechaza(): void
    {
        SiteSetting::first() ?: SiteSetting::create(['site_name' => 'CERSEU']);

        Livewire::test(ConfiguracionDelSitio::class)
            ->fillForm(['popup_retardo_ms' => 300000])
            ->call('guardar')
            ->assertHasFormErrors(['popup_retardo_ms']);
    }

    /**
     * El panel avisa de cuánto se va a recortar la imagen.
     *
     * El marco de la portada es 4:5 y se rellena con `cover`: una imagen con
     * otra proporción pierde bordes, a veces media línea de texto. El modelo ya
     * sabía calcularlo, pero el panel nuevo no lo enseñaba en ninguna parte, así
     * que el recorte solo se descubría mirando la portada ya publicada.
     */
    public function test_el_listado_avisa_del_recorte(): void
    {
        $anuncio = Anuncio::create([
            'titulo' => 'Cartel apaisado',
            'imagen' => 'anuncios/apaisado.webp',
            'imagen_ancho' => 1200,
            'imagen_alto' => 900,
            'orden' => 0,
            'is_visible' => true,
        ]);

        $this->assertTrue($anuncio->recorte_notable);

        Livewire::test(ListAnuncios::class)
            ->assertCanSeeTableRecords([$anuncio])
            ->assertSee('Se recorta 40%');
    }

    /** Y dice la medida que hace falta, donde se elige el fichero. */
    public function test_el_formulario_dice_la_resolucion_que_hace_falta(): void
    {
        $this->get('/panel/anuncios/create')
            ->assertOk()
            ->assertSee(Anuncio::ANCHO_RECOMENDADO . ' × ' . Anuncio::ALTO_RECOMENDADO . ' px', false);
    }
}
