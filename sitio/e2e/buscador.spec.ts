import { test, expect } from '@playwright/test';
import { abrirBuscador } from './utiles';

/**
 * El buscador y, sobre todo, sus destinos.
 *
 * Un índice que ofrece rutas que el sitio no genera es la misma clase de fallo
 * que tenía la navegación: el resultado aparece, se pulsa y se llega a un 404.
 * En un sitio estático eso no lo detecta nada salvo comprobarlo.
 */
test.describe('Buscador', () => {
    test('ninguna ruta del índice lleva a un 404', async ({ page, request }) => {
        await page.goto('/buscar');

        const indice = await (await request.get('/indice-busqueda.json')).json();
        const rutas: string[] = [
            ...new Set(indice.map((i: { url: string }) => i.url)),
        ].filter((url): url is string => typeof url === 'string' && url.startsWith('/'));

        expect(rutas.length).toBeGreaterThan(10);

        for (const ruta of rutas) {
            const respuesta = await request.get(ruta);
            expect(respuesta.status(), `${ruta} responde ${respuesta.status()}`).toBe(200);
        }
    });

    test('encuentra sin tildes y agrupa por categoría', async ({ page }) => {
        await page.goto('/buscar');

        // «redaccion» debe encontrar «Redacción»: si la normalización se
        // pierde, el buscador deja de servir para quien escribe sin tildes,
        // que es la mayoría.
        await page.fill('#q', 'redaccion');

        const resumen = page.locator('.resumen-busqueda');
        await expect(resumen).toContainText('resultados para');

        const enlaces = page.locator('.resultados-busqueda li a');
        await expect(enlaces.first()).toContainText('Redacción');
        await expect(page.locator('.resultados-busqueda h2').first()).toBeVisible();
    });

    test('avisa cuando no hay resultados en vez de quedarse en blanco', async ({ page }) => {
        await page.goto('/buscar');
        await page.fill('#q', 'zzzzquenoexiste');

        await expect(page.locator('.resumen-busqueda')).toContainText('Sin resultados');
        await expect(page.locator('.resultados-busqueda')).toContainText('otro término');
    });

    test('el término de la URL se busca al abrir la página', async ({ page }) => {
        await page.goto('/buscar?q=talleres');

        await expect(page.locator('#q')).toHaveValue('talleres');
        await expect(page.locator('.resumen-busqueda')).toContainText('para «talleres»');
    });

    test('la página de resultados no se indexa', async ({ page }) => {
        await page.goto('/buscar');

        await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
            'content',
            /noindex/
        );
    });

    test('el buscador de la cabecera funciona con teclado', async ({ page }) => {
        await page.goto('/');

        // La lupa despliega el campo, en las dos anchuras.
        const campo = await abrirBuscador(page);
        await campo.fill('admis');

        const lista = page.locator('#buscador-sugerencias');
        await expect(lista).toBeVisible();
        await expect(campo).toHaveAttribute('aria-expanded', 'true');

        await campo.press('ArrowDown');
        await expect(campo).toHaveAttribute('aria-activedescendant', 'sugerencia-0');

        // Enter sobre una sugerencia marcada navega a ella.
        await campo.press('Enter');
        await expect(page).toHaveURL(/\/(talleres|cursos|especializaciones|admision)/);
    });

    test('Escape cierra las sugerencias', async ({ page }) => {
        await page.goto('/');

        const campo = await abrirBuscador(page);
        await campo.fill('admis');
        await expect(page.locator('#buscador-sugerencias')).toBeVisible();

        await campo.press('Escape');
        await expect(page.locator('#buscador-sugerencias')).toBeHidden();
        await expect(campo).toHaveAttribute('aria-expanded', 'false');
    });
});

test.describe('Fichas de docentes', () => {
    test('la plana docente lleva a la ficha y la ficha a lo que dicta', async ({ page }) => {
        await page.goto('/plana-docente');

        const primero = page.locator('a[href^="/profesores/"]').first();
        const nombre = (await primero.textContent())?.trim() ?? '';
        await primero.click();

        await expect(page.locator('main h1')).toContainText(nombre);

        // La ficha existe para contar algo que el listado no cuenta. Hoy eso
        // es lo que dicta: si desapareciera, la página repetiría el nombre y
        // nada más.
        await expect(page.locator('main')).toContainText('Dicta');
        await expect(page.locator('main a[href^="/cursos/"], main a[href^="/talleres/"]').first()).toBeVisible();
    });

    /**
     * El campo tiene que leerse.
     *
     * Sus colores estaban pensados para fondo azul —texto blanco sobre
     * `bg-white/10`—, heredados de cuando la cabecera era una banda oscura. Al
     * pasar el buscador a un panel blanco quedo blanco sobre blanco: se podia
     * escribir y no se veia nada de lo escrito. Ni el build ni las pruebas
     * decian una palabra, porque el campo seguia estando ahi y funcionando.
     *
     * No es una auditoria de contraste; es la guarda concreta de que el texto no
     * se confunda con su propio fondo.
     */
    test('lo que se escribe se ve', async ({ page }) => {
        await page.goto('/');
        const campo = await abrirBuscador(page);
        await campo.fill('redaccion');

        const colores = await campo.evaluate((el) => {
            const c = getComputedStyle(el);
            return { texto: c.color, fondo: c.backgroundColor };
        });

        expect(colores.texto).not.toBe(colores.fondo);
        // Y que el fondo sea de verdad un color, no el transparente que dejaria
        // ver lo que haya debajo.
        expect(colores.fondo).not.toMatch(/rgba\(0, 0, 0, 0\)|transparent/);
    });

    test('resalta lo que coincide y ofrece ir a todos los resultados', async ({ page }) => {
        await page.goto('/');
        const campo = await abrirBuscador(page);
        await campo.fill('redaccion');

        const lista = page.locator('#buscador-sugerencias');
        await expect(lista).toBeVisible();

        // «redaccion» sin tilde tiene que resaltar «Redaccion» con ella: la
        // comparacion va sobre el texto normalizado, pero el corte se hace sobre
        // el original.
        const marcas = lista.locator('mark');
        expect(await marcas.count()).toBeGreaterThan(0);
        expect((await marcas.first().textContent())?.toLowerCase()).toContain('redacc');

        // Ultima fila: lo que no cabe en las sugerencias esta en /buscar.
        await expect(lista.locator('li').last()).toContainText('Ver todos los resultados');
    });

    test('sin resultados lo dice a la vista, no solo al lector de pantalla', async ({ page }) => {
        await page.goto('/');
        const campo = await abrirBuscador(page);
        await campo.fill('zzzzqqqq');

        await expect(page.locator('#buscador-vacio')).toBeVisible();
        await expect(page.locator('#buscador-sugerencias')).toBeHidden();
    });
});
