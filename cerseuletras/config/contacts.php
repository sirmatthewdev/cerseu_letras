<?php

/**
 * ==========================================================
 *  CONTACTOS — SOLO VALORES DE RESPALDO
 * ==========================================================
 *
 * Los datos de contacto que muestra el sitio se editan desde el panel
 * (Configuración → Contacto) y se leen con:
 *
 *     \App\Models\SiteSetting::contacto('general'|'admision'|'tramites'|'telefono'|'whatsapp')
 *
 * Este fichero solo se usa cuando el campo correspondiente está vacío en la
 * base de datos, p. ej. en una instalación recién creada. NO edites aquí para
 * cambiar lo que ve el visitante: hazlo en el panel, o quedará desincronizado
 * (que es justo el problema que este cambio vino a resolver).
 *
 * El enlace de WhatsApp se deriva del teléfono; el valor de abajo solo actúa
 * si no hay teléfono configurado.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Correo Principal / General
    |--------------------------------------------------------------------------
    | Este correo se muestra como contacto principal en varias páginas.
    */
    'general' => 'cerseu.letras@unmsm.edu.pe',

    /*
    |--------------------------------------------------------------------------
    | Correo de Admisión
    |--------------------------------------------------------------------------
    | Para consultas sobre proceso de admisión, inscripciones, pagos, etc.
    */
    'admision' => 'cerseu.letras@unmsm.edu.pe',

    /*
    |--------------------------------------------------------------------------
    | Correo de Trámites
    |--------------------------------------------------------------------------
    | Para consultas sobre trámites, grados, títulos, certificados.
    */
    'tramites' => 'cerseu.letras@unmsm.edu.pe',

    /*
    |--------------------------------------------------------------------------
    | Correo de Inversión / Pagos
    |--------------------------------------------------------------------------
    | Para consultas sobre costos, pagos y asuntos económicos.
    */
    'pagos' => 'cerseu.letras@unmsm.edu.pe',

    /*
    |--------------------------------------------------------------------------
    | Teléfono Principal
    |--------------------------------------------------------------------------
    */
    'telefono' => '994 706 417',

    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    | Enlace directo a WhatsApp. Formato: https://wa.me/51994706417
    */
    'whatsapp' => 'https://wa.me/51994706417',

];
