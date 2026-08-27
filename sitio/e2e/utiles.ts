import type { Page } from '@playwright/test';

/**
 * Deja el menú principal alcanzable, en la anchura que sea.
 *
 * En escritorio la navegación está siempre a la vista; por debajo de `lg` vive
 * en un panel que abre el botón de las tres rayas. Las pruebas tienen que
 * recorrer el camino real del visitante, no saltárselo, y este ayudante evita
 * repetir la bifurcación en cada una.
 */
export async function abrirMenu(page: Page) {
    const boton = page.locator('#abrir-menu');

    if (await boton.isVisible()) {
        if ((await boton.getAttribute('aria-expanded')) !== 'true') {
            await boton.click();
        }
        return page.locator('#menu-movil');
    }

    return page.locator('header nav[aria-label="Principal"]');
}

/**
 * Abre el buscador de la cabecera y devuelve su campo.
 *
 * La lupa despliega el campo: una caja de búsqueda permanente le robaba sitio
 * al menú y al logotipo.
 */
export async function abrirBuscador(page: Page) {
    const boton = page.locator('#abrir-buscador');

    if ((await boton.getAttribute('aria-expanded')) !== 'true') {
        await boton.click();
    }

    return page.locator('#buscador-q');
}

/**
 * Despliega un apartado del menú y devuelve su lista.
 *
 * Los tipos de oferta cuelgan de «Formación», así que llegar a «Cursos» desde la
 * cabecera son dos gestos y no uno. Las pruebas recorren el camino del visitante
 * —abrir y luego pulsar—, que además es la única forma de comprobar que el
 * desplegable abre de verdad.
 */
export async function abrirApartado(page: Page, etiqueta: string) {
    const navegacion = await abrirMenu(page);

    // En el panel estrecho el apartado es un <details>; en escritorio, un botón
    // gobernado por Alpine.
    const resumen = navegacion.locator('summary', { hasText: etiqueta });

    if (await resumen.count()) {
        const abierto = await resumen.evaluate((el) => el.closest('details')?.open === true);
        if (!abierto) await resumen.click();
        return navegacion;
    }

    const boton = navegacion.locator('button', { hasText: etiqueta }).first();
    await boton.click();
    return navegacion;
}
