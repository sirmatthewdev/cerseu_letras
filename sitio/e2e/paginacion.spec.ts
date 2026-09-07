import { expect, test } from '@playwright/test';

/**
 * Paginación de los listados.
 *
 * Antes no había: /cursos servía las 39 fichas de golpe, 145 kB de HTML con una
 * imagen por tarjeta. Lo que se comprueba aquí no es que salgan los números
 * —eso lo diría cualquier captura— sino que la navegación funcione y que no se
 * pierda ni se repita ninguna ficha por el camino, que es el fallo que tiene
 * una paginación mal calculada y que nadie nota hasta que falta un curso.
 */
test.describe('Paginación', () => {
    /*
     * Los enlaces a fichas, sin los de la propia paginacion — que viven dentro
     * de la misma seccion y tambien empiezan por /cursos/. Contarlos daba 42
     * donde hay 39.
     */
    const fichas = (page: import('@playwright/test').Page) =>
        page.locator('#oferta a[href^="/cursos/"]').evaluateAll((as) =>
            as
                .map((a) => a.getAttribute('href'))
                .filter((h): h is string => !!h && !h.startsWith('/cursos/pagina/'))
        );

    test('reparte las fichas sin perder ni repetir ninguna', async ({ page, request }) => {
        // Cuántas hay de verdad, según la API: la cuenta no se escribe a mano
        // aquí, o la prueba dejaría de valer al publicar el curso número 40.
        const publicados = (await (await request.get('/api/v1/programas?tipo=cursos')).json()).data;
        const total = publicados.length;

        const vistas = new Set<string>();
        let pagina = 1;

        for (;;) {
            await page.goto(pagina === 1 ? '/cursos' : `/cursos/pagina/${pagina}`);
            for (const href of await fichas(page)) vistas.add(href);

            const siguiente = page.locator('a[rel="next"]');
            if ((await siguiente.count()) === 0) break;

            pagina++;
            // Tope de seguridad: si la paginación se enlazara en bucle, esto
            // corta en vez de dejar la prueba dando vueltas.
            expect(pagina).toBeLessThan(20);
        }

        // `/cursos/admision` cuelga del mismo prefijo y no es una ficha.
        vistas.delete('/cursos/admision');

        expect(vistas.size).toBe(total);
    });

    test('se puede ir y volver con los enlaces', async ({ page }) => {
        await page.goto('/cursos');

        await expect(page.locator('a[rel="prev"]')).toHaveCount(0);

        await page.locator('a[rel="next"]').click();
        await expect(page).toHaveURL(/\/cursos\/pagina\/2/);

        // La pagina actual se anuncia, no solo se pinta distinta. Se busca
        // dentro de la navegacion de paginas: el menu de la cabecera tambien
        // marca su apartado con `aria-current`, y sin acotar habria dos.
        await expect(
            page.locator('nav[aria-label="Paginación"] [aria-current="page"]')
        ).toHaveText('2');

        await page.locator('a[rel="prev"]').click();
        await expect(page).toHaveURL(/\/cursos$/);
    });

    /**
     * El formulario del hero ofrece TODOS los programas, no los doce de la
     * página. Quien entra por la página 3 tiene que poder pedir información de
     * cualquiera: recortarlo con la paginación sería esconder oferta.
     */
    test('el formulario ofrece toda la oferta en cualquier pagina', async ({ page, request }) => {
        const total = (await (await request.get('/api/v1/programas?tipo=cursos')).json()).data.length;

        for (const url of ['/cursos', '/cursos/pagina/3']) {
            await page.goto(url);
            const opciones = page.locator('form select[name="programa"] option');
            // Una opción más: el «Selecciona…» inicial.
            expect(await opciones.count()).toBe(total + 1);
        }
    });

    test('las paginas siguientes no se indexan', async ({ page }) => {
        await page.goto('/cursos');
        await expect(page.locator('meta[name="robots"]')).toHaveCount(0);

        await page.goto('/cursos/pagina/2');
        await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
    });

    test('la plana docente tambien pagina', async ({ page }) => {
        await page.goto('/plana-docente');
        const primera = await page.locator('a[href^="/profesores/"]').count();

        await page.locator('a[rel="next"]').click();
        await expect(page).toHaveURL(/\/plana-docente\/pagina\/2/);

        const segunda = await page.locator('a[href^="/profesores/"]').count();
        expect(primera).toBeGreaterThan(0);
        expect(segunda).toBeGreaterThan(0);
    });
});
