import { test, expect } from '@playwright/test';

/**
 * Orden de los bloques de inversión (Obs. N.º 2).
 *
 * Lo fijó la Unidad y antes lo comprobaba una prueba de PHP sobre el HTML que
 * pintaba Blade. Ahora el orden lo decide InversionPrograma.astro, así que hay
 * que mirarlo sobre el sitio construido.
 *
 * Hoy ningún programa tiene importes cargados, así que la prueba se salta sola
 * y vuelve a comprobar en cuanto la Unidad cargue el primero: es preferible a
 * borrarla y perder el requisito.
 */
test('los bloques de inversión van en el orden que pidió la Unidad', async ({ page, request }) => {
    const programas = await (await request.get('/indice-busqueda.json')).json();

    const fichas = programas
        .filter((i: { url: string }) => /^\/(talleres|cursos|especializaciones)\/[^/]+$/.test(i.url))
        .map((i: { url: string }) => i.url);

    let conInversion: string | null = null;

    for (const ruta of fichas) {
        await page.goto(ruta);
        if (await page.getByRole('heading', { name: 'Inversión económica' }).count()) {
            conInversion = ruta;
            break;
        }
    }

    test.skip(conInversion === null, 'Ningún programa tiene inversión cargada todavía.');

    const titulos = await page
        .locator('h3')
        .filter({ hasText: /Precio|Modalidades de pago|Pago de constancia|Costo por matrícula|Costo por módulo|Condiciones de pago/ })
        .allTextContents();

    const esperado = [
        'Precio',
        'Modalidades de pago',
        'Pago de constancia',
        'Costo por matrícula',
        'Costo por módulo',
        'Condiciones de pago',
    ].filter((bloque) => titulos.some((t) => t.includes(bloque)));

    const encontrado = titulos.map((t) => t.trim());

    expect(encontrado).toEqual(esperado);
});
