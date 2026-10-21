import { expect, test, type APIRequestContext } from '@playwright/test';

const TIPOS = ['talleres', 'cursos', 'especializaciones'] as const;

/**
 * Cuántas fichas publicadas tiene cada tipo, leído del índice del buscador.
 *
 * Las pruebas de estado vacío daban por hecho que no había talleres, y dejaron
 * de pasar el día que el CERSEU publicó el primero. La oferta la decide el
 * panel, no la prueba: se pregunta al sitio qué hay.
 */
async function publicadosPorTipo(request: APIRequestContext): Promise<Record<string, number>> {
    const indice: { url: string }[] = await (await request.get('/indice-busqueda.json')).json();
    const cuentas: Record<string, number> = Object.fromEntries(TIPOS.map((t) => [t, 0]));

    for (const { url } of indice) {
        const [, tipo, slug] = url.split('/');
        if (tipo in cuentas && slug && slug !== 'admision' && slug !== 'pagina') {
            cuentas[tipo]++;
        }
    }

    return cuentas;
}

/**
 * El fallo que motiva este fichero: los 39 cursos existían en el HTML, la
 * página respondía 200 y la suite de PHP pasaba entera — y no se veía ni uno,
 * porque el umbral del IntersectionObserver era inalcanzable para una grilla
 * tan alta. Solo se detecta comprobando que la tarjeta esté VISIBLE, no que
 * esté presente.
 */
test.describe('La oferta se ve, no solo existe', () => {
    test('las tarjetas de cursos son visibles tras entrar en pantalla', async ({ page }) => {
        await page.goto('/cursos');

        const tarjetas = page.getByRole('article');
        await expect(tarjetas.first()).toBeVisible();

        // La última también: el fallo dejaba visibles las primeras y ocultas
        // las demás según la altura de la grilla.
        const ultima = tarjetas.last();
        await ultima.scrollIntoViewIfNeeded();
        await expect(ultima).toBeVisible();
    });

    test('el listado muestra los cursos publicados', async ({ page }) => {
        await page.goto('/cursos');
        await expect(page.getByRole('article')).not.toHaveCount(0);
    });

    test('un tipo sin oferta explica que está vacío en vez de quedarse en blanco', async ({ page, request }) => {
        const cuentas = await publicadosPorTipo(request);
        const vacio = TIPOS.find((t) => cuentas[t] === 0);

        test.skip(vacio === undefined, 'Todos los tipos tienen oferta publicada.');

        await page.goto(`/${vacio}`);
        await expect(page.getByText('Próximamente')).toBeVisible();
    });

    /**
     * La portada tiene su propia prueba, y mira la OPACIDAD.
     *
     * Playwright considera visible un elemento con `opacity: 0` —tiene caja y
     * no está `hidden`—, así que `toBeVisible()` no vio ninguno de los dos
     * fallos de este proyecto: ni el IntersectionObserver del sitio anterior ni
     * el `gsap.from` que dejó los 23 elementos animados de la portada en
     * opacidad 0. Lo que hay que comprobar es que se vean EN EL MOMENTO en que
     * la sección entra en pantalla, no después de haber recorrido la página
     * entera, que es cuando ya se ha revelado todo igualmente.
     */
    test('la oferta de la portada se ve al entrar en pantalla, no despues', async ({ page }) => {
        await page.goto('/');

        const seccion = page.getByRole('heading', { name: 'Nuestra oferta' });
        await seccion.scrollIntoViewIfNeeded();

        // Un segundo: lo que tarda alguien en mirar. La red de seguridad del
        // módulo actúa a los cuatro, así que este margen comprueba la
        // animación de verdad y no el rescate.
        await page.waitForTimeout(1000);

        // Lo que importa no es un grupo concreto: es que NADA de lo que se ve
        // esté a medio revelar. Se mide sobre lo que ocupa la pantalla en ese
        // instante, que es lo que ve el visitante.
        const enPantalla = await page.evaluate(() =>
            [...document.querySelectorAll('[data-revelar]')]
                .filter((el) => {
                    const caja = el.getBoundingClientRect();
                    return caja.bottom > 0 && caja.top < window.innerHeight;
                })
                .map((el) => ({
                    clase: (el.className || '').toString().slice(0, 40),
                    opacidad: Number(getComputedStyle(el).opacity),
                }))
        );

        expect(enPantalla.length, 'No hay nada animado en pantalla que comprobar').toBeGreaterThan(
            0
        );
        expect(
            enPantalla.filter((e) => e.opacidad < 0.99),
            'Hay elementos invisibles dentro de la pantalla'
        ).toEqual([]);
    });

    test('las secciones de la portada se ven aunque no llegue GSAP', async ({ page }) => {
        // Si la animación no carga, el contenido tiene que estar igual de
        // visible: nada se oculta desde CSS.
        await page.route('**/gsap*.js', (ruta) => ruta.abort());
        await page.goto('/');

        await expect(page.getByRole('heading', { name: 'Nuestra oferta' })).toBeVisible();

        const opacidades = await page.evaluate(() =>
            [...document.querySelectorAll('[data-revelar]')].map((el) =>
                Number(getComputedStyle(el).opacity)
            )
        );

        expect(opacidades.filter((o) => o < 0.99)).toEqual([]);
    });

    /**
     * El filtro de la portada: un botón por tipo más «Todos», como en el sitio
     * anterior. Lo que se comprueba es que filtre de verdad —que cambiar de
     * pestaña cambie las tarjetas— y que un tipo sin oferta explique el hueco
     * en vez de dejar el panel en blanco.
     */
    test('el filtro de la portada tiene los cuatro botones y filtra', async ({ page, request }) => {
        const cuentas = await publicadosPorTipo(request);

        await page.goto('/');

        const pestanas = page.getByRole('tab');
        await expect(pestanas).toHaveCount(4);

        // Arranca en el primer tipo CON oferta, no en el primero a secas: el
        // sitio anterior abría en Talleres cuando no había ninguno, y lo
        // primero que se veía era el aviso de que no hay nada.
        const inicial = TIPOS.find((t) => cuentas[t] > 0) ?? 'todos';
        await expect(page.locator(`#filtro-${inicial}`)).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator(`#panel-${inicial}`).getByRole('article').first()).toBeVisible();

        // Cada pestaña cambia el panel: con oferta enseña tarjetas, sin ella
        // explica el hueco en vez de dejarlo en blanco.
        for (const tipo of TIPOS) {
            await page.locator(`#filtro-${tipo}`).click();
            const panel = page.locator(`#panel-${tipo}`);

            if (cuentas[tipo] > 0) {
                await expect(panel.getByRole('article').first()).toBeVisible();
            } else {
                await expect(panel).toContainText('Todavía no hay');
            }

            for (const otro of TIPOS.filter((t) => t !== tipo)) {
                await expect(page.locator(`#panel-${otro}`)).toBeHidden();
            }
        }

        await page.getByRole('tab', { name: /Todos/ }).click();
        await expect(page.locator('#panel-todos').getByRole('article').first()).toBeVisible();
    });
});