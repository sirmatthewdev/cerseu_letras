<?php

namespace App\Filament\Componentes;

use App\Support\OptimizadorImagen;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Campo de imagen que pasa por el optimizador del proyecto.
 *
 * Existe para que no haya cuatro copias de la misma configuración. No es un
 * atajo estético: el guardado de serie de Filament deja el fichero tal como
 * llegó, y las imágenes que sube la Unidad rondan los 4 MB. `OptimizadorImagen`
 * las convierte a WebP y las reduce, que es lo que hacía el panel anterior y lo
 * que evita que una portada pese como el resto de la página junta.
 *
 * Si la conversión falla —un formato que la extensión no sabe leer—, el
 * optimizador guarda el original: se prefiere una imagen pesada a una ficha sin
 * imagen.
 */
class ImagenOptimizada
{
    public static function make(
        string $campo,
        string $carpeta,
        ?int $anchoMaximo = null,
        string $etiqueta = 'Imagen'
    ): FileUpload {
        return FileUpload::make($campo)
            ->label($etiqueta)
            ->image()
            ->imagePreviewHeight('160')
            ->disk('public')
            ->directory($carpeta)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->maxSize(5120)
            ->helperText(
                $anchoMaximo
                    ? "Se convierte a WebP y se reduce a {$anchoMaximo} px de ancho."
                    : 'Se convierte a WebP.'
            )
            ->saveUploadedFileUsing(
                fn (TemporaryUploadedFile $archivo): string => OptimizadorImagen::guardar(
                    $archivo,
                    $carpeta,
                    'public',
                    $anchoMaximo
                )
            );
    }
}
