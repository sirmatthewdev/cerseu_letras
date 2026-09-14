import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faArrowDown,
    faArrowRight,
    faArrowUpRightFromSquare,
    faEnvelope,
    faPhone,
} from '@fortawesome/free-solid-svg-icons';

/**
 * El icono de un botón, deducido de lo que hace su enlace.
 *
 * Los botones llevaban todos la misma flecha a la derecha, y no todos van a la
 * derecha: «Ver la oferta», en el hero de los listados, apunta a `#oferta` y lo
 * que hace es bajar por la misma página. Una flecha lateral ahí promete otra
 * pantalla y entrega un salto de scroll.
 *
 * Varios de esos destinos los escribe la Unidad desde el panel —las acciones de
 * la portada, el botón de «Cómo inscribirte»—, así que el icono no se puede
 * elegir a mano en la plantilla: el día que alguien cambie `/cursos` por
 * `#oferta` la flecha se quedaría mintiendo. Se deduce de la URL y así no hay
 * nada que recordar.
 *
 *   `#algo`          baja por esta misma página        ↓
 *   `mailto:`        abre el correo                    ✉
 *   `tel:`           llama                             ☎
 *   `http(s)://`     sale a otro sitio                 ↗
 *   lo demás         otra página de este sitio         →
 */
export function iconoDeAccion(url: string): IconDefinition {
    const destino = url.trim();

    if (destino.startsWith('#')) return faArrowDown;
    if (destino.startsWith('mailto:')) return faEnvelope;
    if (destino.startsWith('tel:')) return faPhone;
    if (/^https?:\/\//i.test(destino)) return faArrowUpRightFromSquare;

    return faArrowRight;
}

/**
 * ¿El icono va detrás del texto?
 *
 * Las flechas de continuación se leen después de la frase —«Ver la oferta →»—;
 * las que nombran el medio van delante, como la etiqueta de un campo: «✉
 * Escribir al CERSEU». Es la misma regla que ya seguían a mano los botones con
 * `faUserPlus`.
 */
export function iconoAlFinalDeAccion(url: string): boolean {
    const destino = url.trim();

    return !destino.startsWith('mailto:') && !destino.startsWith('tel:');
}
