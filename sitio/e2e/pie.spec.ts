import { expect, test } from '@playwright/test';

/**
 * El pie.
 *
 * Las dos comprobaciones de aquí guardan cosas que ya se rompieron una vez.
 */
test.describe('Pie', () => {
    /**
     * La columna de navegación se quedó con una sola entrada al agrupar la
     * oferta bajo «Formación»: filtraba los elementos de primer nivel que
     * tuvieran `enlace`, y al pasar casi todos a ser desplegables sin enlace
     * propio dejó de encontrarlos. No fallaba nada —ni build, ni tipos, ni una
     * prueba—; simplemente el pie se quedó sin enlaces.
     */
    test('la navegacion lista los destinos del menu, no uno suelto', async ({ page }) => {
        await page.goto('/');

        const enlaces = page.locator('footer a[href^="/"]');
        const total = await enlaces.count();

        // El menú sembrado tiene nueve destinos internos; se pide holgadamente
        // menos para que editar el menú desde el panel no rompa la prueba, pero
        // lo bastante para que «una sola entrada» no pase.
        expect(total).toBeGreaterThanOrEqual(5);

        const destinos = await enlaces.evaluateAll((as) =>
            as.map((a) => a.getAttribute('href') ?? '')
        );

        // Sin repetidos: aplanar el menú junta padres e hijos, y sin deduplicar
        // el mismo destino saldría dos veces.
        expect(new Set(destinos).size).toBe(destinos.length);
    });

    /**
     * Los iconos de las redes toman el color de su marca al pasar el ratón. En
     * móvil no hay ratón que pasar, así que ahí no se comprueba.
     */
    test('los iconos de las redes toman el color de su marca', async ({ page, isMobile }) => {
        test.skip(Boolean(isMobile), 'Sin raton no hay estado «encima» que medir.');
        await page.goto('/');

        const redes = page.locator('footer .red-icono');
        const total = await redes.count();
        expect(total).toBeGreaterThan(0);

        for (let i = 0; i < total; i++) {
            const red = redes.nth(i);
            const nombre = (await red.getAttribute('aria-label')) ?? `red ${i}`;

            const reposo = await red.evaluate((el) => getComputedStyle(el).color);
            await red.hover();

            // El color llega por una transicion de 300 ms: leerlo de inmediato
            // devuelve todavia el de reposo.
            await expect
                .poll(async () => red.evaluate((el) => getComputedStyle(el).color), {
                    message: `«${nombre}» no cambia de color al pasar el raton`,
                })
                .not.toBe(reposo);
        }
    });
});
