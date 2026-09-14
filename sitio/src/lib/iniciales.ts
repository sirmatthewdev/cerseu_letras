/**
 * Monogramas para cuando no hay fotografía.
 *
 * Las tarjetas de coordinador ya lo hacían: sin retrato propio, las iniciales
 * en serif sobre el degradado de marca. Funciona porque ocupa el mismo cuadrado
 * que la foto —la rejilla no se descuadra el día que la Unidad suba unas
 * cuantas y otras no— y porque cada tarjeta sale distinta de la de al lado.
 *
 * Aquí vive la regla, compartida por las personas y por los programas, que la
 * necesitan de formas distintas: el nombre de una persona son dos palabras
 * llenas y el de un programa viene con prefijo de tipo, artículos y el módulo
 * al final.
 */

/** Sin acentos ni signos: lo que queda es lo que puede ser una inicial. */
function letras(palabra: string): string {
    return palabra.replace(/[^\p{L}\p{N}]/gu, '');
}

export function inicialesDePersona(nombre: string): string {
    return nombre
        .split(/\s+/)
        .map(letras)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
}

/*
 * Palabras que no dan inicial.
 *
 * Artículos, preposiciones y conjunciones: sin esto «Redacción de Tesis» sale
 * «RD» y «Fonética y Fonología» sale «FY», que no dicen nada del programa. Van
 * también las portuguesas —hay un curso dictado en portugués— y los verbos
 * vacíos que abren título («es», «ser»).
 */
const SIN_INICIAL = new Set([
    'a', 'al', 'ante', 'con', 'como', 'contra', 'de', 'del', 'desde', 'e', 'el', 'en', 'entre',
    'es', 'ser', 'hacia', 'hasta', 'la', 'las', 'lo', 'los', 'o', 'para', 'por', 'que', 'se',
    'segun', 'según', 'sin', 'sobre', 'su', 'sus', 'tras', 'u', 'un', 'una', 'unas', 'unos', 'y',
    // Portugués
    'da', 'das', 'do', 'dos', 'na', 'no', 'nas', 'nos', 'em', 'e',
]);

/*
 * El tipo, al principio del título.
 *
 * «Curso-taller: APA sin clichés» y «Seminario: Introducción a la Metafísica»
 * empiezan diciendo lo que ya dice la fila de datos de la tarjeta —«Curso ·
 * Virtual · 20 horas académicas»—, así que como inicial solo repetiría. Lo que
 * identifica al programa empieza después.
 */
const PREFIJO_DE_TIPO =
    /^(?:curso[-\s]?taller|curso|taller|seminario|especializaci[oó]n|diplomado|programa)\s*(?:[:\-–—]|\bde\b)\s*/i;

/** El módulo o el nivel, si el título lo lleva al final. */
function nivelDe(nombre: string): string | null {
    const coincide = nombre
        .trim()
        .match(/(?:\(\s*(?:m[óo]dulo|nivel)\s+([IVX]{1,4})\s*\)|\s([IVX]{1,4}))$/i);

    return coincide ? (coincide[1] ?? coincide[2]).toUpperCase() : null;
}

/**
 * Monograma de un programa: dos iniciales y, si lo lleva, el módulo.
 *
 * El módulo va porque sin él la mitad del catálogo colisiona, y colisiona
 * justo donde mas se nota: «Redacción de Tesis I» y «Redacción de Tesis II»
 * salen consecutivas en la rejilla, y dos «RT» pegadas la una a la otra son
 * exactamente el problema que se venia a arreglar. Con el módulo son «RT I» y
 * «RT II».
 *
 * No pretende ser un identificador. El nombre completo va debajo, a dos lineas
 * de distancia; esto es un ancla visual para que la rejilla no se lea como una
 * sola tarjeta repetida.
 */
export function inicialesDePrograma(nombre: string): string {
    const nivel = nivelDe(nombre);

    const cuerpo = nombre
        .trim()
        // El módulo, fuera: sus letras no son iniciales de nada.
        .replace(/\(\s*(?:m[óo]dulo|nivel)\s+[IVX]{1,4}\s*\)\s*$/i, '')
        .replace(/\s[IVX]{1,4}$/i, '')
        .replace(PREFIJO_DE_TIPO, '');

    const palabras = cuerpo
        .split(/[\s:;,.·—–\-/()«»"']+/)
        .map(letras)
        .filter((p) => p.length > 0 && !SIN_INICIAL.has(p.toLowerCase()));

    const iniciales = palabras
        .slice(0, 2)
        .map((p) => p[0].toUpperCase())
        .join('');

    // Un titulo que se quedara sin palabras con inicial no deberia existir, pero
    // un monograma vacio dejaria un rectangulo mudo: antes que eso, la primera
    // letra del nombre tal cual llego.
    const marca = iniciales || letras(nombre.trim())[0]?.toUpperCase() || '·';

    return nivel ? `${marca} ${nivel}` : marca;
}

/**
 * Un angulo para el degradado, deducido del slug.
 *
 * Con 39 programas las iniciales se repiten —hay cuatro «Didáctica de la
 * Enseñanza»— y dos monogramas iguales con el mismo fondo son dos tarjetas
 * iguales. Cuatro inclinaciones bastan para que no lo parezcan, y siguen siendo
 * los dos colores de la marca: no se introduce ningún color nuevo.
 *
 * Del slug y no al azar: el sitio se construye entero en cada publicación, y un
 * fondo que cambiara de inclinación en cada build seria ruido en el `dist/`.
 */
export function anguloDelFondo(slug: string): number {
    const angulos = [115, 145, 200, 245];

    // Multiplicando y no sumando: la rejilla va por orden alfabetico, asi que
    // las que se parecen caen juntas —«redaccion-de-tesis-i» y «-ii» son
    // vecinas—, y una suma de codigos les da casi el mismo numero. Con el
    // factor, un caracter de diferencia cambia el resultado entero.
    let cuenta = 0;
    for (const caracter of slug) cuenta = (cuenta * 31 + caracter.charCodeAt(0)) % 100003;

    return angulos[cuenta % angulos.length];
}
