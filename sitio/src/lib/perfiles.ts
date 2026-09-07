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

/**
 * Los tres perfiles, existan o no.
 *
 * `perfilesDe` devuelve solo los cargados, que es lo que necesita quien quiere
 * enlazarlos. Esta devuelve siempre los tres, con la direccion en `null` cuando
 * falta, porque la plana docente los enseña todos: los que hay en color y los
 * que faltan apagados.
 *
 * La razon es de gestion, no de diseño. Con los huecos a la vista se ve de un
 * vistazo a que docentes les falta ORCID o CTI Vitae; ocultandolos, una ficha
 * incompleta se ve igual de terminada que una completa, y nadie la completa
 * nunca.
 */
export function perfilesConHuecos(docente: FuentePerfiles): {
    etiqueta: PerfilAcademico['etiqueta'];
    url: string | null;
}[] {
    const cargados = new Map(perfilesDe(docente).map((p) => [p.etiqueta, p.url]));

    return (['ORCID', 'CTI Vitae', 'LinkedIn'] as const).map((etiqueta) => ({
        etiqueta,
        url: cargados.get(etiqueta) ?? null,
    }));
}
