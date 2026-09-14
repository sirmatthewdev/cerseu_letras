<?php

/*
 * Lo que contesta el flujo de recuperación de contraseña.
 *
 * Los devuelve el gestor de contraseñas de Laravel y salen por
 * `x-auth-session-status` o como error del campo del correo, según el caso.
 *
 * `user` —el correo no está registrado— se muestra tal cual: este formulario no
 * es público, solo lo alcanza quien ya tiene cuenta en el panel.
 */

return [

    'reset' => 'Su contraseña ha sido restablecida.',
    'sent' => 'Le hemos enviado por correo el enlace para restablecer la contraseña.',
    'throttled' => 'Espere un momento antes de volver a intentarlo.',
    'token' => 'Este enlace para restablecer la contraseña no es válido.',
    'user' => 'No encontramos ninguna cuenta con ese correo electrónico.',

];
