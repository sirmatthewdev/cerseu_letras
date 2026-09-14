import { describe, expect, it } from 'vitest';
import {
    faArrowDown,
    faArrowRight,
    faArrowUpRightFromSquare,
    faEnvelope,
    faPhone,
} from '@fortawesome/free-solid-svg-icons';
import { iconoDeAccion, iconoAlFinalDeAccion } from './iconos';

/**
 * El icono de un botón sale de lo que hace su enlace.
 *
 * Existe por un botón concreto: «Ver la oferta», en el hero de los listados,
 * llevaba una flecha a la derecha y apunta a `#oferta`, o sea que baja por la
 * misma página. La flecha prometía otra pantalla.
 *
 * Se prueba aquí y no en el navegador porque la mitad de estos destinos los
 * escribe la Unidad desde el panel: comprobarlo con Playwright exigiría sembrar
 * una configuración por cada forma de URL, y lo que hay que fijar es la regla,
 * no la pantalla.
 */
describe('iconoDeAccion', () => {
    it('baja cuando el enlace es un ancla de la misma pagina', () => {
        expect(iconoDeAccion('#oferta')).toBe(faArrowDown);
    });

    it('avanza cuando el enlace es otra pagina del sitio', () => {
        expect(iconoDeAccion('/cursos')).toBe(faArrowRight);
        expect(iconoDeAccion('/cursos/normas-apa-i')).toBe(faArrowRight);
    });

    it('sale cuando el enlace es de otro sitio', () => {
        expect(iconoDeAccion('https://letras.unmsm.edu.pe')).toBe(faArrowUpRightFromSquare);
        expect(iconoDeAccion('HTTP://ejemplo.pe')).toBe(faArrowUpRightFromSquare);
    });

    it('nombra el medio cuando el enlace no es una pagina', () => {
        expect(iconoDeAccion('mailto:cerseu.letras@unmsm.edu.pe')).toBe(faEnvelope);
        expect(iconoDeAccion('tel:+5119140331')).toBe(faPhone);
    });

    /*
     * El panel guarda texto libre, así que una URL con espacios alrededor no es
     * hipotética: sin recortar, `' #oferta'` caería en la rama de «otra página».
     */
    it('no se despista con los espacios de alrededor', () => {
        expect(iconoDeAccion('  #oferta  ')).toBe(faArrowDown);
    });
});

describe('iconoAlFinalDeAccion', () => {
    it('pone la flecha de continuacion detras del texto', () => {
        expect(iconoAlFinalDeAccion('/cursos')).toBe(true);
        expect(iconoAlFinalDeAccion('#oferta')).toBe(true);
        expect(iconoAlFinalDeAccion('https://ejemplo.pe')).toBe(true);
    });

    it('pone delante el icono que nombra el medio', () => {
        expect(iconoAlFinalDeAccion('mailto:cerseu.letras@unmsm.edu.pe')).toBe(false);
        expect(iconoAlFinalDeAccion('tel:+5119140331')).toBe(false);
    });
});
