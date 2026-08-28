<?php

namespace App\Filament\Resources\Anuncios\Concerns;

use App\Support\OptimizadorImagen;

/**
 * Guarda las medidas reales de la imagen del anuncio.
 *
 * `imagen_ancho` e `imagen_alto` no son decorativos: el componente de la portada
 * los usa para reservar el hueco exacto antes de que la imagen cargue. Sin
 * ellos, el anuncio aparece de golpe y empuja el contenido — un salto de diseño
 * en lo primero que ve el visitante.
 *
 * Se leen del fichero YA GUARDADO y no del que se subió, porque el optimizador
 * lo redimensiona: las medidas del original no son las que acaba teniendo. Es
 * exactamente lo que hacía `AdminAnuncioController`; sin traerlo, el formulario
 * nuevo habría dejado las dos columnas vacías sin que nada avisara.
 *
 * Vive en un trait porque hacen falta al crear y al editar, y son el mismo
 * cálculo.
 */
trait MideLaImagen
{
    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function conMedidas(array $datos): array
    {
        $ruta = $datos['imagen'] ?? null;

        if (! is_string($ruta) || $ruta === '') {
            return $datos;
        }

        [$ancho, $alto] = OptimizadorImagen::medidas($ruta);

        $datos['imagen_ancho'] = $ancho;
        $datos['imagen_alto'] = $alto;

        return $datos;
    }
}
