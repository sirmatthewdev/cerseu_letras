import { describe, expect, it } from 'vitest';
import { anguloDelFondo, inicialesDePersona, inicialesDePrograma } from './iniciales';

/**
 * El monograma que llevan las tarjetas sin fotografía.
 *
 * Los casos no son inventados: son los títulos que la Unidad tiene publicados.
 * Se prueban aquí y no en el navegador porque lo que hay que sujetar es la
 * regla —qué palabra da inicial y cuál no—, y comprobarlo con Playwright
 * exigiría sembrar un programa por cada forma de título.
 */
describe('inicialesDePersona', () => {
    it('toma la inicial del nombre y la del primer apellido', () => {
        expect(inicialesDePersona('Luciana Aliaga Balletta')).toBe('LA');
        expect(inicialesDePersona('Yony Cárdenas')).toBe('YC');
    });

    it('sobrevive a un nombre de una sola palabra', () => {
        expect(inicialesDePersona('Rocha')).toBe('R');
    });
});

describe('inicialesDePrograma', () => {
    it('salta los artículos y las preposiciones', () => {
        // Sin esto serían «RD» y «FY», que no dicen nada del programa.
        expect(inicialesDePrograma('Redacción de Tesis I')).toBe('RT I');
        expect(inicialesDePrograma('Fonética y Fonología del Inglés II')).toBe('FF II');
    });

    it('salta el tipo con el que empieza el título', () => {
        // El tipo ya lo dice la fila de datos de la tarjeta.
        expect(inicialesDePrograma('Seminario: Introducción a la «Metafísica» de Aristóteles')).toBe(
            'IM'
        );
        expect(
            inicialesDePrograma('Curso-taller: APA sin clichés: más allá de la norma en la producción investigativa')
        ).toBe('AC');
        expect(inicialesDePrograma('Curso-taller de elaboración de preguntas de opción múltiple')).toBe(
            'EP'
        );
        expect(inicialesDePrograma('Curso: Literatura tusán y nikkei')).toBe('LT');
    });

    /*
     * El módulo es lo único que separa a media docena de pares, y esos pares
     * salen consecutivos en la rejilla: sin él, dos tarjetas idénticas pegadas.
     */
    it('conserva el módulo, que es lo que separa a los pares', () => {
        expect(inicialesDePrograma('Composición Avanzada del Inglés I')).toBe('CA I');
        expect(inicialesDePrograma('Composición Avanzada del Inglés II')).toBe('CA II');
        expect(
            inicialesDePrograma(
                'Capacitación sobre terapia del lenguaje: Trastornos del Lenguaje y Evaluación Lingüística (Módulo II)'
            )
        ).toBe('CT II');
    });

    it('no se atraganta con los signos de apertura', () => {
        // Sin limpiar el signo, la primera inicial salía «¿».
        expect(inicialesDePrograma('¿Puede ser la lingüística queer y feminista?')).toBe('PL');
        expect(inicialesDePrograma('Educar el Pensamiento: ¿por qué filosofía hoy?')).toBe('EP');
    });

    it('entiende el título que viene en portugués', () => {
        expect(
            inicialesDePrograma('Didática do Português como Língua Pluricêntrica na Formação Docente')
        ).toBe('DP');
    });

    it('devuelve algo aunque el título no tenga ninguna palabra con inicial', () => {
        expect(inicialesDePrograma('de la')).toBe('D');
        expect(inicialesDePrograma('   ')).toBe('·');
    });
});

describe('anguloDelFondo', () => {
    it('da siempre el mismo ángulo al mismo programa', () => {
        expect(anguloDelFondo('redaccion-de-tesis-i')).toBe(anguloDelFondo('redaccion-de-tesis-i'));
    });

    /*
     * Lo que de verdad importa: la rejilla va por orden alfabético, así que los
     * módulos de un mismo programa caen uno al lado del otro. Si compartieran
     * inclinación además de iniciales, serían dos tarjetas iguales pegadas.
     */
    it('separa a los vecinos que solo se diferencian en el módulo', () => {
        expect(anguloDelFondo('redaccion-de-tesis-i')).not.toBe(anguloDelFondo('redaccion-de-tesis-ii'));
        expect(anguloDelFondo('didactica-de-la-ensenanza-ninos-i')).not.toBe(
            anguloDelFondo('didactica-de-la-ensenanza-ninos-ii')
        );
    });

    it('se queda dentro de las cuatro inclinaciones previstas', () => {
        const permitidos = [115, 145, 200, 245];

        for (const slug of ['normas-apa-i', 'oratoria-y-teatro-i', 'educar-el-pensamiento', '']) {
            expect(permitidos).toContain(anguloDelFondo(slug));
        }
    });
});
