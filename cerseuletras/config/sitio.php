<?php

/**
 * Enlace con el sitio público en Astro.
 *
 * Hoy apunta al servicio de build del propio Docker Compose. Cuando el build
 * pase a CI, cambia esta URL —y el token— sin tocar una línea de código: el
 * contrato es el mismo, una petición autenticada que encarga la reconstrucción.
 */
return [
    'reconstruccion' => [
        // Vacío desactiva el aviso: útil en las pruebas y en una instalación
        // que todavía sirva el sitio con Blade.
        'url' => env('CERSEU_BUILD_URL', 'http://build:4322/reconstruir'),
        'token' => env('CERSEU_BUILD_TOKEN', ''),
        // Margen para agrupar ráfagas: quien edita guarda varias veces seguidas.
        'espera_segundos' => (int) env('CERSEU_BUILD_ESPERA', 60),
    ],

    /**
     * Vista previa de borradores.
     *
     * El token lo comparten Laravel y el contenedor de build. Vacío —que es lo
     * que trae una instalación recién hecha— desactiva la función entera: es
     * preferible que la vista previa no funcione a que un descuido de
     * configuración deje los borradores a la vista de cualquiera.
     */
    'vista_previa' => [
        'token' => env('CERSEU_VISTA_PREVIA_TOKEN', ''),
        // A dónde se le encarga el render. Mismo servicio que reconstruye el
        // sitio: ya existe en producción y ya está autenticado.
        'url' => env('CERSEU_VISTA_PREVIA_URL', 'http://build:4322'),
        // Un build completo son unos 17 segundos medidos; el margen cubre una máquina
        // cargada sin dejar la petición colgada para siempre.
        'espera_maxima' => (int) env('CERSEU_VISTA_PREVIA_ESPERA', 120),
    ],
];
