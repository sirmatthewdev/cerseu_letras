<?php

namespace Tests\Feature;

use App\Support\OptimizadorImagen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Optimización de las imágenes que se suben al sitio.
 *
 * Antes entraba por `POST /admin/anuncios`, que ya no existe. No se movió a
 * Filament sino aquí abajo, al optimizador: la regla nunca fue del panel. El
 * panel solo elegía el fichero, y probarla a través de un formulario ataba una
 * regla de imágenes a la herramienta de turno — que es justo lo que la dejó sin
 * red al retirar la anterior.
 *
 * Que el panel *use* el optimizador se comprueba aparte, en
 * `PanelSubidaDeImagenesTest`.
 */
class OptimizacionImagenesTest extends TestCase
{
    public function test_una_imagen_enorme_se_reduce_al_ancho_maximo(): void
    {
        Storage::fake('public');

        $ruta = OptimizadorImagen::guardar(
            UploadedFile::fake()->image('cartel.jpg', 2000, 2500),
            'anuncios'
        );

        [$ancho, $alto] = OptimizadorImagen::medidas($ruta);

        // El personal no tiene por qué saber de compresión: se hace al subir.
        $this->assertSame(OptimizadorImagen::ANCHO_MAXIMO, $ancho);
        $this->assertStringEndsWith('.webp', $ruta);

        // La proporción se mantiene: 2000×2500 es 4:5, y el alto tiene que
        // seguir siéndolo tras reducir.
        $this->assertSame((int) round($ancho * 2500 / 2000), $alto);
    }

    public function test_una_imagen_ya_pequena_no_se_agranda(): void
    {
        Storage::fake('public');

        $ruta = OptimizadorImagen::guardar(
            UploadedFile::fake()->image('mini.jpg', 500, 625),
            'anuncios'
        );

        $this->assertSame([500, 625], OptimizadorImagen::medidas($ruta));
    }

    public function test_el_archivo_optimizado_existe_en_el_disco(): void
    {
        Storage::fake('public');

        $ruta = OptimizadorImagen::guardar(
            UploadedFile::fake()->image('c.jpg', 2000, 2500),
            'anuncios'
        );

        Storage::disk('public')->assertExists($ruta);
    }

    public function test_rechaza_optimizar_lo_que_no_cabe_en_memoria(): void
    {
        // GD descomprime la imagen entera: sin este guardián, un cartel muy
        // grande provocaba un error fatal por memoria agotada —no capturable—
        // y el administrador veía una página en blanco al guardar. En ese caso
        // el archivo se guarda tal cual: mejor pesado que perdido.
        //
        // Se comprueba con números y no con un archivo real porque fabricar
        // una imagen de 6000×8000 agota la memoria del propio test.
        // Un cartel corriente sí cabe.
        $this->assertTrue(OptimizadorImagen::cabeEnMemoria(1200, 1500));

        // ~48 y ~144 megapíxeles: a 4 bytes por píxel se van muy por encima de
        // cualquier límite razonable, así que se rechazan siempre.
        $this->assertFalse(OptimizadorImagen::cabeEnMemoria(6000, 8000));
        $this->assertFalse(OptimizadorImagen::cabeEnMemoria(12000, 12000));
    }
}
