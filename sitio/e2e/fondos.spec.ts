import { expect, test } from '@playwright/test';

/**
 * Los fondos de las secciones de la portada.
 *
 * La banda de marca se quedó en un azul plano al migrar: el degradado de azul a
 * dorado que tenía el sitio anterior se perdió por el camino y nadie se enteró,
 * porque un color plano no rompe nada. Esto lo guarda.
 */
test.describe('Fondos de seccion', () => {
    test('la banda va en degradado, no en color plano', async ({ page }) => {
        await page.goto('/');

        const banda = page.locator('.banda-marca');
        await expect(banda).toBeVisible();

        const fondo = await banda.evaluate((el) => getComputedStyle(el).backgroundImage);

        // Un color plano deja `background-image: none`.
        expect(fondo).toContain('gradient');

        // Y que sea de azul a dorado y no un degradado cualquiera: el ultimo
        // tramo tiene que ser mas calido que el primero, o sea mas rojo que
        // azul. Se compara sobre los propios topes del degradado calculado.
        const calido = await banda.evaluate((el) => {
            const imagen = getComputedStyle(el).backgroundImage;
            const colores = imagen.match(/rgba?\([^)]+\)/g) ?? [];
            if (colores.length < 2) return null;

            const canal = (c: string) => (c.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
            const primero = canal(colores[0]!);
            const ultimo = canal(colores[colores.length - 1]!);

            return {
                // Azul al principio: el canal azul manda sobre el rojo.
                empiezaFrio: primero[2]! > primero[0]!,
                // Dorado al final: el rojo manda sobre el azul.
                acabaCalido: ultimo[0]! > ultimo[2]!,
            };
        });

        expect(calido).not.toBeNull();
        expect(calido!.empiezaFrio, 'la banda deberia empezar en azul').toBe(true);
        expect(calido!.acabaCalido, 'la banda deberia acabar en dorado').toBe(true);
    });

    /**
     * Las capas decorativas se mueven al pasar. Es lo único de este proyecto que
     * se anima sin red de seguridad, y puede permitírselo porque no hay
     * contenido dentro: si GSAP no llega, la capa se queda quieta y no se pierde
     * nada. La prueba comprueba justo eso —que se mueve— sin que ningún texto
     * dependa de ello.
     */
    test('los fondos derivan al pasar, y el contenido no', async ({ page }) => {
        await page.goto('/');

        const capa = page.locator('.banda-marca .fondo-seccion');
        await expect(capa).toHaveCount(1);

        // La capa es decorativa: nada de lo que hay dentro debe leerse.
        await expect(capa).toHaveAttribute('aria-hidden', 'true');
        expect(await capa.evaluate((el) => el.textContent?.trim())).toBe('');

        await page.locator('.banda-marca').scrollIntoViewIfNeeded();
        const titular = page.locator('.banda-marca h2');
        const antes = await capa.evaluate((el) => getComputedStyle(el).transform);
        const textoAntes = await titular.evaluate((el) => getComputedStyle(el).transform);

        await page.mouse.wheel(0, 500);

        // GSAP se descarga aparte y el `scrub` va con retardo: hay que darle
        // margen en vez de leer el valor de inmediato.
        await expect
            .poll(async () => capa.evaluate((el) => getComputedStyle(el).transform), {
                timeout: 8000,
                message: 'la capa de fondo no llega a moverse',
            })
            .not.toBe(antes);

        // Y el texto se queda donde estaba: lo que deriva es el fondo, no la
        // seccion. Si esto cambiara, el parrafo estaria bailando al pasar.
        expect(await titular.evaluate((el) => getComputedStyle(el).transform)).toBe(textoAntes);
    });
});
