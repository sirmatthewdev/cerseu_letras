import { expect, test } from '@playwright/test';

/**
 * El desplegable de la barra de navegación.
 *
 * Antes era un `<details>`: solo abría al pulsar, y una vez abierto se quedaba
 * así aunque te fueras con el ratón a otra parte. Lo que se comprueba aquí es
 * justo eso —que abra al pasar, que cierre al salir y que pulsar no lo deje
 * colgado— y que nada de ello se consiga a costa del teclado.
 */
test.describe('Desplegables de la cabecera', () => {
    // El menú de escritorio no existe en el proyecto móvil, donde la
    // navegación va en un panel aparte que se abre con el botón.
    test.skip(({ isMobile }) => Boolean(isMobile), 'La barra de escritorio no está en móvil.');

    const primerDesplegable = (page: import('@playwright/test').Page) =>
        page.locator('nav[aria-label="Principal"] > div').first();

    test('abre al pasar el raton y cierra al salir', async ({ page }) => {
        await page.goto('/');

        const grupo = primerDesplegable(page);
        const boton = grupo.getByRole('button');
        const lista = grupo.getByRole('list');

        await expect(lista).toBeHidden();

        await boton.hover();
        await expect(lista).toBeVisible();
        await expect(boton).toHaveAttribute('aria-expanded', 'true');

        // Salir del desplegable lo cierra: el logotipo está lejos del menú.
        await page.locator('header a[href="/"]').first().hover();
        await expect(lista).toBeHidden();
        await expect(boton).toHaveAttribute('aria-expanded', 'false');
    });

    test('pulsar no lo deja colgado al retirar el raton', async ({ page }) => {
        await page.goto('/');

        const grupo = primerDesplegable(page);
        const boton = grupo.getByRole('button');
        const lista = grupo.getByRole('list');

        await boton.click();
        await expect(lista).toBeVisible();

        await page.locator('header a[href="/"]').first().hover();
        await expect(lista).toBeHidden();
    });

    test('funciona con teclado, sin depender del raton', async ({ page }) => {
        await page.goto('/');

        const grupo = primerDesplegable(page);
        const boton = grupo.getByRole('button');
        const lista = grupo.getByRole('list');

        await boton.focus();
        await page.keyboard.press('Enter');
        await expect(lista).toBeVisible();

        // Escape cierra y devuelve el foco al botón, para no perder el sitio.
        await page.keyboard.press('Escape');
        await expect(lista).toBeHidden();
        await expect(boton).toBeFocused();

        // La flecha abajo también abre, que es lo que espera quien navega así.
        await page.keyboard.press('ArrowDown');
        await expect(lista).toBeVisible();
    });

    test('al bajar, la barra se vuelve cristal y no tapa el contenido', async ({ page }) => {
        await page.goto('/');

        const barra = page.locator('#barra-navegacion');
        await page.mouse.wheel(0, 600);

        await expect(page.locator('header.cabecera')).toHaveClass(/bajada/);

        // El fondo llega por una transicion de 300 ms: leerlo de inmediato
        // devuelve el valor de partida, que es transparente.
        await expect
            .poll(async () => barra.evaluate((el) => getComputedStyle(el).backgroundColor))
            .toMatch(/rgba\(255, 255, 255, 0\.[0-8]/);

        const estilo = await barra.evaluate((el) => {
            const c = getComputedStyle(el);
            // Las dos, y sin `||`: `backdropFilter` devuelve la cadena 'none'
            // cuando no aplica, que es truthy, así que un `||` nunca llegaba a
            // mirar la prefijada y la prueba media la propiedad equivocada.
            const filtros = [c.backdropFilter, (c as any).webkitBackdropFilter]
                .filter((v) => v && v !== 'none')
                .join(' ');
            return { fondo: c.backgroundColor, filtro: filtros };
        });

        // Translúcido de verdad: con 0.95 el desenfoque estaba puesto y no se
        // veía, porque no quedaba fondo que desenfocar.
        expect(estilo.filtro).toContain('blur');
    });
});

/**
 * La cápsula flotante y sus dos estados.
 *
 * Arriba es transparente y se apoya sobre el hero; al bajar se comprime y se
 * vuelve cristal. La compresión es real —`max-width`, relleno y separación— y no
 * un `scaleX`, que deformaría el logotipo y el texto: por eso lo que se mide es
 * el ancho de la caja y no una escala.
 */
test.describe('La capsula se comprime al bajar', () => {
    const medir = (page: import('@playwright/test').Page) =>
        page.locator('#barra-navegacion').evaluate((el) => {
            const r = el.getBoundingClientRect();
            const c = getComputedStyle(el);
            return {
                ancho: Math.round(r.width),
                alto: Math.round(r.height),
                arriba: Math.round(r.top),
                fondo: c.backgroundColor,
                // Que no se haya conseguido escalando: un `scaleX` dejaria aqui
                // una matriz distinta de la identidad.
                transformacion: c.transform,
            };
        });

    test('arriba es transparente y se apoya en el hero', async ({ page }) => {
        await page.goto('/');
        const inicial = await medir(page);

        // Sin fondo propio: lo que se ve detras es el hero, no una banda.
        expect(inicial.fondo).toMatch(/rgba\(0, 0, 0, 0\)|transparent/);
    });

    test('al bajar encoge de verdad, sin escalar el contenido', async ({ page, isMobile }) => {
        await page.goto('/');
        const inicial = await medir(page);

        await page.mouse.wheel(0, 600);
        await expect(page.locator('header.cabecera')).toHaveClass(/bajada/);
        // La transicion dura 420 ms: medir antes devuelve el valor de partida.
        await page.waitForTimeout(700);

        const final = await medir(page);

        expect(final.alto).toBeLessThan(inicial.alto);
        expect(final.transformacion).toMatch(/none|matrix\(1, 0, 0, 1, 0, 0\)/);

        // El ancho solo puede encoger donde la ventana da holgura para 1280; por
        // debajo, el limite lo pone la propia ventana y no hay nada que comprimir.
        if (!isMobile && inicial.ancho >= 1280) {
            expect(final.ancho).toBeLessThan(inicial.ancho);
        }

        // Despegada del borde: comprimida tiene que flotar, no pegarse arriba.
        expect(final.arriba).toBeGreaterThan(8);
    });
});

/**
 * Lo que se ve en cada anchura.
 *
 * Esto guarda un fallo concreto: las reglas de la cabecera declaraban `display`,
 * que tiene mas especificidad que las utilidades `hidden` y `lg:hidden`, y las
 * anulaba. El resultado fue la hamburguesa visible en escritorio y, en movil, el
 * boton de inscripcion empujandola fuera de la pantalla: el movil se quedaba sin
 * manera de abrir el menu.
 */
test.describe('Cada anchura muestra lo que le toca', () => {
    test('la hamburguesa y el menu no se pisan', async ({ page, isMobile }) => {
        await page.goto('/');

        const hamburguesa = page.locator('#abrir-menu');
        const menuEscritorio = page.locator('nav[aria-label="Principal"]');
        // El de la barra, no el que va dentro del panel movil.
        const inscribirse = page.locator('.capsula .cta-capsula');

        if (isMobile) {
            await expect(hamburguesa).toBeVisible();
            await expect(menuEscritorio).toBeHidden();

            // En la barra estrecha no cabe: su sitio es el panel. Cuando se
            // colaba aqui, empujaba a la hamburguesa fuera de la pantalla.
            await expect(inscribirse).toBeHidden();

            // Y dentro de la ventana, no empujada fuera por el boton de al lado.
            const caja = await hamburguesa.boundingBox();
            const ancho = page.viewportSize()?.width ?? 0;
            expect(caja).not.toBeNull();
            expect(caja!.x + caja!.width).toBeLessThanOrEqual(ancho);
        } else {
            await expect(hamburguesa).toBeHidden();
            await expect(menuEscritorio).toBeVisible();
            await expect(inscribirse).toBeVisible();
        }
    });

    test('el menu queda centrado y no toca las acciones', async ({ page, isMobile }) => {
        test.skip(Boolean(isMobile), 'En movil el menu va en un panel aparte.');
        await page.goto('/');

        for (const bajada of [false, true]) {
            if (bajada) {
                await page.mouse.wheel(0, 600);
                await expect(page.locator('header.cabecera')).toHaveClass(/bajada/);
                await page.waitForTimeout(700);
            }

            const medidas = await page.evaluate(() => {
                const caja = (s: string) => document.querySelector(s)!.getBoundingClientRect();
                const nav = caja('nav[aria-label="Principal"]');
                const logo = caja('.capsula > a');
                const acciones = caja('.capsula > div:last-of-type');
                return {
                    desvio: Math.round(nav.left + nav.width / 2 - window.innerWidth / 2),
                    izquierda: Math.round(nav.left - logo.right),
                    derecha: Math.round(acciones.left - nav.right),
                };
            });

            // Centrado respecto a la ventana, no al hueco entre logotipo y
            // acciones: repartir el sobrante lo dejaba unos 48 px a la izquierda.
            expect(Math.abs(medidas.desvio)).toBeLessThanOrEqual(2);

            // Y sin solaparse con lo que tiene a los lados.
            expect(medidas.izquierda).toBeGreaterThan(0);
            expect(medidas.derecha).toBeGreaterThan(0);
        }
    });
});
