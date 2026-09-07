import { describe, expect, it } from 'vitest';
import { perfilesDe } from './perfiles';

/**
 * Perfiles académicos de un docente.
 *
 * Esta función existe por un fallo concreto: la ficha enlazaba el valor tal como
 * estaba guardado, y ORCID se publica como «0000-0003-1753-7448» a secas. Ese
 * `href` lo lee el navegador como una ruta del propio sitio, así que el enlace
 * llevaba a un 404 en vez de al perfil — y solo se habría notado cuando la
 * Unidad cargara el primero, porque hoy ninguno de los 20 docentes tiene
 * perfiles.
 *
 * Es la primera prueba unitaria del proyecto. Se añade Vitest por ella: los
 * campos son texto libre en el panel, van a llegar de todas las formas
 * imaginables, y comprobar eso a través del navegador exigiría datos sembrados.
 */
describe('perfilesDe', () => {
    it('completa el ORCID cuando llega solo el identificador', () => {
        const [orcid] = perfilesDe({ orcid: '0000-0003-1753-7448' });

        expect(orcid.url).toBe('https://orcid.org/0000-0003-1753-7448');
    });

    it('respeta el ORCID cuando ya viene como direccion', () => {
        const [orcid] = perfilesDe({ orcid: 'https://orcid.org/0000-0003-1753-7448' });

        expect(orcid.url).toBe('https://orcid.org/0000-0003-1753-7448');
    });

    it('mantiene intacta una direccion de CTI Vitae con parametros', () => {
        const url =
            'https://ctivitae.concytec.gob.pe/appDirectorioCTI/VerDatosInvestigador.do?id_investigador=70318';

        expect(perfilesDe({ cti_vitae: url })[0].url).toBe(url);
    });

    it('anade el protocolo cuando falta', () => {
        expect(perfilesDe({ linkedin: 'linkedin.com/in/alguien' })[0].url).toBe(
            'https://linkedin.com/in/alguien'
        );
    });

    it('no inventa enlaces con los campos vacios', () => {
        expect(perfilesDe({})).toEqual([]);
        expect(perfilesDe({ orcid: '', cti_vitae: null, linkedin: undefined })).toEqual([]);
        // Un campo con solo espacios es lo que deja un formulario donde alguien
        // escribio y borro: no es un perfil.
        expect(perfilesDe({ orcid: '   ' })).toEqual([]);
    });

    it('devuelve solo los que existen, en orden', () => {
        const perfiles = perfilesDe({
            orcid: '0000-0002-1825-0097',
            linkedin: 'https://linkedin.com/in/alguien',
        });

        expect(perfiles.map((p) => p.etiqueta)).toEqual(['ORCID', 'LinkedIn']);
    });

    /**
     * La propiedad que de verdad importa: pase lo que pase, lo que sale es una
     * direccion absoluta. Si alguna vez devolviera algo relativo, el enlace
     * volveria a caer dentro del sitio.
     */
    it('siempre devuelve direcciones absolutas', () => {
        const entradas = [
            '0000-0003-1753-7448',
            'orcid.org/0000-0003-1753-7448',
            'https://orcid.org/0000-0003-1753-7448',
            '/0000-0003-1753-7448',
        ];

        for (const orcid of entradas) {
            const [perfil] = perfilesDe({ orcid });
            expect(perfil.url).toMatch(/^https?:\/\//);
        }
    });
});
