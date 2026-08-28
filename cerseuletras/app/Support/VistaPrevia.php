<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Vista previa de borradores.
 *
 * Con Blade, guardar y ver eran el mismo paso. Al separar el sitio, el HTML se
 * genera en un build y la API solo sirve lo publicado, así que un borrador no
 * existe en ninguna parte hasta que se publica — y publicarlo es justo lo que
 * el editor quiere evitar mientras lo revisa.
 *
 * Esto abre una rendija: una petición que traiga el token correcto ve también
 * los borradores. La rendija es estrecha a propósito.
 *
 * - **Sin token configurado no hay vista previa.** Si `CERSEU_VISTA_PREVIA_TOKEN`
 *   está vacío, esto devuelve `false` pase lo que pase. Sin esta guarda, una
 *   instalación sin configurar aceptaría una cabecera vacía y publicaría todos
 *   sus borradores a quien preguntara.
 * - **La comparación es `hash_equals`.** Un `===` sobre cadenas se rinde en el
 *   primer byte distinto, y ese tiempo se mide: con suficientes intentos se
 *   adivina el token carácter a carácter.
 * - **Quien la usa no se cachea.** La respuesta lleva `no-store`, porque una
 *   respuesta con borradores guardada por un intermediario se acaba sirviendo a
 *   alguien que no traía token.
 *
 * El token vive donde el de reconstrucción: en el entorno de Laravel y en el
 * del contenedor de build, que es el único que lo usa. Nunca llega al navegador.
 */
class VistaPrevia
{
    /** Cabecera que la transporta. */
    public const CABECERA = 'X-Vista-Previa';

    /**
     * ¿Esta petición puede ver borradores?
     */
    public static function activa(?Request $peticion = null): bool
    {
        $esperado = (string) config('sitio.vista_previa.token', '');

        if ($esperado === '') {
            return false;
        }

        $recibido = (string) ($peticion ?? request())->header(self::CABECERA, '');

        return $recibido !== '' && hash_equals($esperado, $recibido);
    }
}
