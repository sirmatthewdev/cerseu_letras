import { describe, expect, it } from 'vitest';
import {
    faArrowUpRightFromSquare,
    faCircleInfo,
    faEnvelope,
    faGraduationCap,
    faHouse,
    faPhone,
    faUserPlus,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import { iconoDeAccion } from './iconos';

/**
 * El icono de un botón nombra su acción.
 *
 * Se prueba aquí y no en el navegador porque varios de estos destinos los
 * escribe la Unidad desde el panel: comprobarlo con Playwright exigiría sembrar
 * una configuración por cada forma de URL, y lo que hay que fijar es la
 * correspondencia entre destino e icono, no una pantalla.
 */
describe('iconoDeAccion', () => {
    it('nombra el canal cuando la acción no es una página', () => {
        expect(iconoDeAccion('mailto:cerseu.letras@unmsm.edu.pe')).toBe(faEnvelope);
        expect(iconoDeAccion('tel:+5119140331')).toBe(faPhone);
    });

    it('nombra la sección a la que se va', () => {
        expect(iconoDeAccion('/cursos')).toBe(faGraduationCap);
        expect(iconoDeAccion('/talleres')).toBe(faGraduationCap);
        expect(iconoDeAccion('/plana-docente')).toBe(faUsers);
        expect(iconoDeAccion('/')).toBe(faHouse);
    });

    /*
     * El ancla de «Ver la oferta» lleva a la misma rejilla que `/cursos`: lo
     * que hace el botón es lo mismo, y el icono tiene que decir lo mismo.
     */
    it('trata el ancla de la oferta como la oferta', () => {
        expect(iconoDeAccion('#oferta')).toBe(faGraduationCap);
    });

    /*
     * `/cursos/admision` empieza por `/cursos` y no es ver cursos: es
     * inscribirse. El orden de la tabla es parte de la regla.
     */
    it('distingue inscribirse de ver la oferta', () => {
        expect(iconoDeAccion('/admision')).toBe(faUserPlus);
        expect(iconoDeAccion('/cursos/admision')).toBe(faUserPlus);
        expect(iconoDeAccion('/especializaciones/admision')).toBe(faUserPlus);
    });

    /*
     * La única flecha que sobrevive, y no por dirección: es el signo de «esto
     * abre en otra parte», el mismo que llevan los documentos de la ficha.
     */
    it('avisa de que un enlace externo sale del sitio', () => {
        expect(iconoDeAccion('https://letras.unmsm.edu.pe')).toBe(faArrowUpRightFromSquare);
        expect(iconoDeAccion('HTTP://ejemplo.pe')).toBe(faArrowUpRightFromSquare);
    });

    it('cae en «hay más sobre esto» cuando el destino no dice qué es', () => {
        expect(iconoDeAccion('/cursos/normas-apa-i')).toBe(faCircleInfo);
        expect(iconoDeAccion('/una-pagina-que-no-existe-todavia')).toBe(faCircleInfo);
    });

    it('no se despista con los espacios de alrededor', () => {
        // El panel guarda texto libre: una URL con espacios no es hipotética.
        expect(iconoDeAccion('  /cursos  ')).toBe(faGraduationCap);
    });
});
