import { expect, test } from '@playwright/test';
import { abrirApartado, abrirMenu } from './utiles';

/**
 * El otro fallo: los enlaces internos de la cabecera apuntaban a la aplicación
 * de Laravel, así que pulsar «Cursos» sacaba al visitante del sitio que estaba
 * viendo. Respondía 200 y por eso ninguna comprobación de rutas lo veía.
 */
test.describe('La navegación no saca del sitio', () => {
    test('los enlaces internos de la cabecera son relativos', async ({ page }) => {
        await page.goto('/');

        const destinos = await page
            .locator('header nav a')
            .evaluateAll((enlaces) =>
                enlaces.map((a) => ({
                    href: a.getAttribute('href') ?? '',
                    externo: a.getAttribute('target') === '_blank',
                }))
            );

        const internos = destinos.filter((d) => !d.externo);
        expect(internos.length).toBeGreaterThan(0);

        for (const enlace of internos) {
            expect(enlace.href, `«${enlace.href}» debería ser una ruta, no una URL`).toMatch(/^\//);
        }
    });

    test('pulsar Cursos en la cabecera se queda en el mismo origen', async ({ page }) => {
        await page.goto('/');
        const origen = new URL(page.url()).origin;

        // En móvil el menú va en un panel, y «Cursos» cuelga de «Formación»: se
        // recorre el camino entero, como lo haría el visitante.
        const navegacion = await abrirApartado(page, 'Formación');

        await navegacion.locator('a[href="/cursos"]').first().click();
        await page.waitForURL('**/cursos');

        expect(new URL(page.url()).origin).toBe(origen);
    });

    test('ninguna ruta del menú responde 404', async ({ page, request }) => {
        await page.goto('/');

        const rutas = await page
            .locator('header nav a')
            .evaluateAll((enlaces) =>
                enlaces
                    .map((a) => a.getAttribute('href') ?? '')
                    .filter((h) => h.startsWith('/'))
            );

        for (const ruta of [...new Set(rutas)]) {
            const respuesta = await request.get(ruta);
            expect(respuesta.status(), `${ruta} respondió ${respuesta.status()}`).toBeLessThan(400);
        }
    });

    test('las direcciones anteriores redirigen en vez de romperse', async ({ request }) => {
        // El sitio estuvo publicado como Unidad de Posgrado y hay enlaces
        // sueltos por ahí. Los redirigía Laravel; desde que Nginx sirve el
        // sitio, los redirige quien los recibe, y esto es lo único que puede
        // comprobarlo: en las pruebas de PHP esas direcciones ya no existen.
        const casos = [
            ['/diplomados', '/talleres'],
            ['/diplomados/admision', '/talleres/admision'],
            ['/programas', '/cursos'],
        ];

        for (const [antigua, nueva] of casos) {
            const respuesta = await request.get(antigua, { maxRedirects: 0 });

            expect(respuesta.status(), `${antigua} debería redirigir`).toBe(301);
            expect(respuesta.headers()['location']).toContain(nueva);
        }
    });
});