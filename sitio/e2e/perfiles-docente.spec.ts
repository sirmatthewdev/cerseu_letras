import { expect, test } from '@playwright/test';

/**
 * Perfiles academicos en las tarjetas de docente.
 *
 * La Unidad los quiere visibles siempre —los cargados en su color y los que
 * faltan apagados— porque la rejilla incompleta es la que hace que alguien
 * complete la ficha. Si los huecos se ocultaran, una ficha a medias se veria
 * igual de terminada que una completa y nadie la completaria nunca.
 *
 * Eso se rompe en silencio: basta quitar `conHuecos` de un componente para que
 * la señal desaparezca sin que falle el build, los tipos ni las pruebas
 * unitarias, que solo miran los datos y no lo que se pinta.
 */
test.describe('Perfiles academicos', () => {
    test('cada docente enseña las tres casillas, tenga o no perfiles', async ({ page }) => {
        await page.goto('/plana-docente');

        const fichas = page.locator('[data-ficha-docente]');
        const cuantas = await fichas.count();
        expect(cuantas).toBeGreaterThan(0);

        for (const ficha of await fichas.all()) {
            // Tres casillas por docente: las que enlazan y las vacias juntas.
            await expect(ficha.locator('.enlace-academico')).toHaveCount(3);
        }
    });

    /**
     * Un hueco no lleva a ninguna parte: no puede anunciarse como enlace ni
     * recibir el foco, o quien navega con teclado se come tres paradas muertas
     * por docente.
     */
    test('los huecos no son enlaces ni paran el tabulador', async ({ page }) => {
        await page.goto('/plana-docente');

        const huecos = page.locator('.perfil-vacio');
        expect(await huecos.count()).toBeGreaterThan(0);

        const etiquetas = await huecos.evaluateAll((nodos) =>
            nodos.map((n) => ({
                etiqueta: n.tagName,
                href: n.getAttribute('href'),
                tabindex: n.getAttribute('tabindex'),
            }))
        );

        for (const hueco of etiquetas) {
            expect(hueco.etiqueta).toBe('SPAN');
            expect(hueco.href).toBeNull();
            expect(hueco.tabindex).toBeNull();
        }
    });

    /**
     * El enlace del nombre cubre la tarjeta entera. Sin sacar los iconos por
     * encima, pulsar ORCID abriria la ficha del docente en vez del perfil.
     */
    test('un perfil cargado abre el perfil, no la ficha', async ({ page }) => {
        await page.goto('/plana-docente');

        const cargados = page.locator('a.perfil-cargado');
        if ((await cargados.count()) === 0) {
            // Hoy ninguna ficha tiene perfiles: no hay nada que comprobar, pero
            // en cuanto la Unidad cargue el primero esto empieza a vigilar.
            test.skip(true, 'Ningun docente tiene perfiles cargados todavia');
        }

        const primero = cargados.first();
        await expect(primero).toHaveAttribute('target', '_blank');
        await expect(primero).toHaveAttribute('rel', /noopener/);
        expect(await primero.getAttribute('href')).toMatch(/^https?:\/\//);
    });
});
