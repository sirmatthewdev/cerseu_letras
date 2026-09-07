/**
 * Perfiles académicos de un docente: ORCID, CTI Vitae y LinkedIn.
 *
 * Los tres son texto libre en el panel, así que llegan como venga: unas veces
 * la dirección entera y otras solo el identificador. ORCID en particular se
 * publica casi siempre como «0000-0003-1753-7448» a secas, y guardarlo así es
 * lo natural — lo que no se puede es meterlo en un `href` tal cual, porque el
 * navegador lo interpreta como una ruta del propio sitio y el enlace lleva a un
 * 404 en vez de al perfil.
 *
 * Vive aquí y no dentro del componente porque lo necesitan dos vistas —la
 * tarjeta y la ficha— y dos copias de esta regla acabarían divergiendo.
 */

const ORCID = 'https://orcid.org/';

function normalizar(valor: string | null | undefined, base?: string): string | null {
    const limpio = (valor ?? '').trim();
    if (limpio === '') return null;

    if (limpio.startsWith('http://') || limpio.startsWith('https://')) {
        return limpio;
    }

    // Sin protocolo pero con dominio: solo le falta el «https://».
    if (limpio.includes('.') && !base) {
        return `https://${limpio}`;
    }

    return base ? base + limpio.replace(/^\/+/, '') : `https://${limpio}`;
}

export type PerfilAcademico = {
    etiqueta: 'ORCID' | 'CTI Vitae' | 'LinkedIn';
    url: string;
};

export type FuentePerfiles = {
    orcid?: string | null;
    cti_vitae?: string | null;
    linkedin?: string | null;
};

/**
 * Los perfiles que tiene cargados un docente, ya listos para enlazar.
 *
 * Devuelve solo los que existen: la ficha y la tarjeta ocultan el bloque entero
 * cuando no hay ninguno, en vez de dejar tres huecos.
 */
export function perfilesDe(docente: FuentePerfiles): PerfilAcademico[] {
    return [
        { etiqueta: 'ORCID' as const, url: normalizar(docente.orcid, ORCID) },
        { etiqueta: 'CTI Vitae' as const, url: normalizar(docente.cti_vitae) },
        { etiqueta: 'LinkedIn' as const, url: normalizar(docente.linkedin) },
    ].filter((p): p is PerfilAcademico => Boolean(p.url));
}
