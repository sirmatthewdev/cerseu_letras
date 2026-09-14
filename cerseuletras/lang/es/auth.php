<?php

/*
 * Lo que /login responde cuando no deja entrar.
 *
 * `failed` es el mensaje de un correo o una contraseña que no cuadran, y no
 * distingue cuál de los dos falla: decir «esa cuenta no existe» le confirma a
 * quien prueba correos cuáles están registrados.
 *
 * `throttle` sale a los cinco intentos fallidos, contados por correo e IP en
 * `LoginRequest::throttleKey()`.
 */

return [

    'failed' => 'Las credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Vuelva a intentarlo en :seconds segundos.',

];
