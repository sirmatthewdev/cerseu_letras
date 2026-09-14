import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faAddressBook,
    faArrowUpRightFromSquare,
    faCalendarAlt,
    faCalendarDay,
    faCircleInfo,
    faEnvelope,
    faFilePdf,
    faFileSignature,
    faGraduationCap,
    faHouse,
    faLandmark,
    faMagnifyingGlass,
    faPhone,
    faUserPlus,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';

/**
 * El icono de un botón: lo que hace, no hacia dónde apunta.
 *
 * Los botones llevaban todos la misma flecha a la derecha. Una flecha no dice
 * nada que el propio botón no diga ya —«Ver la oferta» no necesita que le
 * expliquen que va hacia delante—, y con la misma en todos el icono deja de ser
 * información y pasa a ser relleno. El sitio ya tenía el otro criterio escrito
 * en los botones de inscripción, que llevan una persona con un «más»: eso sí
 * nombra la acción. Aquí se extiende al resto.
 *
 * El destino decide porque es lo que se sabe: varias de estas URL las escribe
 * la Unidad desde el panel —las dos acciones de la portada, el botón que cierra
 * «Cómo inscribirte»— y elegirlas a mano en la plantilla significaría que el
 * icono se queda desfasado el día que alguien cambie el enlace.
 *
 * Los iconos son los que el sitio ya usaba en cada sección, no unos nuevos: el
 * birrete es el de la banda de indicadores para los programas, el calendario el
 * de los eventos, el PDF el de los informativos. Así el mismo dibujo significa
 * lo mismo en toda la página.
 */
const POR_DESTINO: [RegExp, IconDefinition][] = [
    // Medios de contacto: el icono nombra el canal que se va a abrir.
    [/^mailto:/i, faEnvelope],
    [/^tel:/i, faPhone],

    // Fuera del sitio. La diagonal es la única flecha que se queda, y no por
    // dirección: es el signo de «esto abre en otra parte», el mismo que llevan
    // los documentos descargables de la ficha.
    [/^https?:\/\//i, faArrowUpRightFromSquare],

    // Secciones. El de admisión va antes que los tipos porque la ruta de una
    // admisión los lleva dentro: `/cursos/admision` es inscribirse, no ver
    // cursos.
    [/\/admision(\/|$)/i, faUserPlus],
    // El listado y sus páginas, no las fichas: `/cursos/normas-apa-i` es un
    // programa concreto, y de ese el botón que lleva a él no tiene que decir
    // «esto es un curso» —eso ya lo dice la fila de datos de la tarjeta— sino
    // «aquí hay más». Cae al icono por defecto.
    [/^(?:#oferta|\/(?:cursos|talleres|especializaciones))(?:\/pagina\/\d+)?$/i, faGraduationCap],
    [/^\/(plana-docente|profesores)(\/|$)/i, faUsers],
    [/^\/eventos(\/|$)/i, faCalendarDay],
    [/^\/informativos(\/|$)/i, faFilePdf],
    [/^\/tramites(\/|$)/i, faFileSignature],
    [/^\/cronograma(\/|$)/i, faCalendarAlt],
    [/^\/directorio(\/|$)/i, faAddressBook],
    [/^\/nosotros(\/|$)/i, faLandmark],
    [/^\/buscar(\/|$)/i, faMagnifyingGlass],
    [/^\/$/, faHouse],
];

/**
 * Para lo que no está en la tabla —una ficha, una página que aún no existe— el
 * círculo de información: dice «aquí hay más sobre esto», que es lo único que
 * se puede afirmar sin saber a dónde lleva.
 */
const POR_DEFECTO = faCircleInfo;

export function iconoDeAccion(url: string): IconDefinition {
    const destino = url.trim();

    for (const [patron, icono] of POR_DESTINO) {
        if (patron.test(destino)) return icono;
    }

    return POR_DEFECTO;
}
