import { defineConfig } from 'vitest/config';

/**
 * Pruebas unitarias del sitio.
 *
 * `include` acotado a `src/` a proposito: los ficheros de `e2e/` son de
 * Playwright, usan su propio `test.describe` y Vitest los recogia y fallaba
 * nueve veces por algo que no es un fallo. Los dos corredores conviven, pero
 * cada uno mira su carpeta.
 *
 *   npm test        las unitarias (Vitest, sobre src/)
 *   npm run test:e2e  las de navegador (Playwright, sobre e2e/)
 */
export default defineConfig({
    test: {
        include: ['src/**/*.test.ts'],
    },
});
