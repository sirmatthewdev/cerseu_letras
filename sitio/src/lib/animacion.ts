/**
 * Capa de animación sobre GSAP.
 *
 * La regla que gobierna este módulo tiene nombre propio en este proyecto: en el
 * sitio anterior un IntersectionObserver con el umbral mal puesto dejó los 39
 * cursos invisibles en móvil, y al traer GSAP el primer intento repitió el
 * fallo con otro mecanismo —`gsap.from` aplica el estado inicial de inmediato,
 * así que todo lo marcado quedaba en opacidad 0 esperando un disparador—.
 *
 * De ahí las tres reglas:
 *
 * 1. **Nada se oculta desde CSS, nunca.** El HTML llega con todo visible. Si
 *    GSAP no carga, si falla la red o si un disparador no llega a dispararse,
 *    la página se ve entera. Es imposible llegar a una sección en blanco.
 *
 * 2. **Solo se anima lo que está fuera de la pantalla al empezar.** A lo que ya
 *    se ve no se le toca la opacidad: ocultarlo para revelarlo es exactamente
 *    cómo se llega a un bloque vacío si algo va mal, y además parpadea.
 *
 * 3. **Red de seguridad.** Pasados unos segundos, cualquier elemento que siga
 *    oculto se revela sin más. Un disparador que no llega no puede costarle
 *    contenido a nadie.
 *
 * Y una cuarta que es de coste, no de corrección: GSAP son unos 110 kB con
 * ScrollTrigger, así que se descarga en su propio fragmento y solo si la página
 * tiene algo que animar — nunca si se pidió `prefers-reduced-motion: reduce`.
 */

const sinMovimiento = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

type Gsap = typeof import('gsap').gsap;
type ScrollTriggerTipo = typeof import('gsap/ScrollTrigger').ScrollTrigger;

let pendiente: Promise<{ gsap: Gsap; ScrollTrigger: ScrollTriggerTipo } | null> | null = null;

async function cargar() {
    if (sinMovimiento()) return null;

    if (!pendiente) {
        pendiente = Promise.all([import('gsap'), import('gsap/ScrollTrigger')])
            .then(([{ gsap }, { ScrollTrigger }]) => {
                gsap.registerPlugin(ScrollTrigger);
                return { gsap, ScrollTrigger };
            })
            .catch(() => {
                pendiente = null;
                return null;
            });
    }

    return pendiente;
}

/** Espera a que la página termine de cargar: las posiciones no son fiables antes. */
function cuandoEsteLista(): Promise<void> {
    if (document.readyState === 'complete') return Promise.resolve();

    return new Promise((listo) => {
        window.addEventListener('load', () => listo(), { once: true });
    });
}

/**
 * Revela al entrar en pantalla lo que empieza fuera de ella.
 */
export async function revelar(selector = '[data-revelar]'): Promise<void> {
    const todos = [...document.querySelectorAll<HTMLElement>(selector)];
    if (todos.length === 0) return;

    // Las medidas se toman con la página ya cargada: calcularlas antes, con las
    // imágenes todavía sin altura, colocaba los disparadores en posiciones que
    // el documento final ya no tenía, y varios no llegaban a dispararse.
    await cuandoEsteLista();

    const modulos = await cargar();
    if (!modulos) return;

    const { gsap, ScrollTrigger } = modulos;
    const alto = window.innerHeight;

    // Solo lo que está por debajo del pliegue. Lo que ya se ve se queda como
    // está: visible.
    const fuera = todos.filter((el) => el.getBoundingClientRect().top > alto * 0.9);
    if (fuera.length === 0) return;

    const grupos = new Map<Element, HTMLElement[]>();

    fuera.forEach((el) => {
        const grupo = el.closest('[data-revelar-grupo]') ?? el.parentElement ?? document.body;
        grupos.set(grupo, [...(grupos.get(grupo) ?? []), el]);
    });

    grupos.forEach((hijos) => {
        gsap.fromTo(
            hijos,
            { opacity: 0, y: 24 },
            {
                opacity: 1,
                y: 0,
                duration: 0.6,
                ease: 'power2.out',
                stagger: 0.08,
                // Marca los que están animándose, para que la red de seguridad
                // sepa a cuáles debe vigilar.
                onStart: () => hijos.forEach((h) => (h.dataset.revelando = '1')),
                onComplete: () => hijos.forEach((h) => delete h.dataset.revelando),
                scrollTrigger: {
                    trigger: hijos[0]!,
                    start: 'top 90%',
                    once: true,
                    invalidateOnRefresh: true,
                },
            }
        );
    });

    // Recalcula posiciones cuando el tipo de letra o una imagen tardía cambian
    // la altura del documento.
    ScrollTrigger.refresh();

    // Red de seguridad: pase lo que pase, a los cuatro segundos nada sigue
    // oculto por culpa de un disparador que no llegó.
    window.setTimeout(() => {
        fuera.forEach((el) => {
            if (Number(getComputedStyle(el).opacity) < 1 && !el.dataset.revelando) {
                gsap.set(el, { opacity: 1, y: 0, clearProps: 'transform' });
            }
        });
    }, 4000);
}

/**
 * Cuenta hasta el número que ya está escrito en el elemento.
 *
 * El valor final vive en el HTML y no en un `data-`: quien no ejecute
 * JavaScript, o llegue con el movimiento reducido, ve la cifra correcta y no un
 * cero.
 */
export async function contar(selector = '[data-contador]'): Promise<void> {
    const elementos = [...document.querySelectorAll<HTMLElement>(selector)];
    if (elementos.length === 0) return;

    await cuandoEsteLista();

    const modulos = await cargar();
    if (!modulos) return;

    const { gsap } = modulos;

    elementos.forEach((el) => {
        const original = el.dataset.contador || el.textContent || '';
        const destino = Number(original.replace(/\D/g, ''));
        if (!Number.isFinite(destino) || destino === 0) return;

        const estado = { valor: 0 };

        gsap.to(estado, {
            valor: destino,
            duration: 1.4,
            ease: 'power2.out',
            scrollTrigger: { trigger: el, start: 'top 95%', once: true },
            onUpdate: () => {
                el.textContent = String(Math.round(estado.valor));
            },
            // Se restituye el texto original: si llevaba sufijo («20+»),
            // redondear lo habría perdido.
            onComplete: () => {
                el.textContent = original;
            },
        });
    });
}

/**
 * Deriva de los fondos de sección al pasar.
 *
 * Lo que se mueve es la capa decorativa, nunca el contenido: son resplandores y
 * una retícula dentro de un `div` vacío y `aria-hidden`. Por eso esta función se
 * salta las tres reglas de arriba sin riesgo — no hay nada que pueda quedarse
 * invisible, porque no hay nada dentro.
 *
 * El `scrub` ata el avance a la barra de desplazamiento en vez de lanzar una
 * animación con duración propia: así el movimiento acompaña a la rueda, que es
 * lo que hace que se lea como profundidad y no como un efecto.
 */
export async function fondos(selector = '[data-fondo-deriva]'): Promise<void> {
    const capas = [...document.querySelectorAll<HTMLElement>(selector)];
    if (capas.length === 0) return;

    await cuandoEsteLista();

    const modulos = await cargar();
    if (!modulos) return;

    const { gsap, ScrollTrigger } = modulos;

    capas.forEach((capa) => {
        const seccion = capa.parentElement;
        if (!seccion) return;

        const deriva = Number(capa.dataset.fondoDeriva) || 60;

        gsap.fromTo(
            capa,
            { yPercent: 0, y: -deriva / 2 },
            {
                y: deriva / 2,
                ease: 'none',
                scrollTrigger: {
                    trigger: seccion,
                    // De cuando la sección asoma por abajo a cuando se va por
                    // arriba: el recorrido completo, no un tramo.
                    start: 'top bottom',
                    end: 'bottom top',
                    scrub: 0.6,
                    invalidateOnRefresh: true,
                },
            }
        );
    });

    // Y el degradado de la banda, que se desplaza en sentido contrario: como su
    // color va en diagonal, mover la posición del fondo hace que el dorado
    // recorra la banda en vez de quedarse clavado en la esquina.
    const bandas = [...document.querySelectorAll<HTMLElement>('[data-banda-degradado]')];

    bandas.forEach((banda) => {
        gsap.fromTo(
            banda,
            { backgroundPosition: '0% 50%' },
            {
                backgroundPosition: '100% 50%',
                ease: 'none',
                scrollTrigger: {
                    trigger: banda,
                    start: 'top bottom',
                    end: 'bottom top',
                    scrub: 0.6,
                    invalidateOnRefresh: true,
                },
            }
        );
    });

    ScrollTrigger.refresh();
}

/** Arranca todo lo animado de la página. */
export function iniciarAnimaciones(): void {
    revelar();
    contar();
    fondos();
}
