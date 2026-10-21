# Correo

**En funcionamiento desde el 21 de octubre de 2026.** Producción envía por
`smtp.gmail.com:587` con una contraseña de aplicación de la cuenta
`cerseu.letras@unmsm.edu.pe` (aplicación «Informatica» en la cuenta de Google).
`php artisan correo:probar` la entregó, y los dos avisos que se habían quedado
en modo `log` (solicitudes del 12 y del 15 de octubre) se reenviaron.

Si la contraseña de aplicación se revoca, o cambia la contraseña de la cuenta,
Google la invalida y los avisos vuelven a fallar. Se ven en *Solicitudes* con la
etiqueta «Aviso no enviado». Basta con generar otra, ponerla en `MAIL_PASSWORD`
y seguir los pasos de abajo.

Lo que sigue es el diagnóstico original, del 2 de agosto de 2026, cuando el
correo estaba en `MAIL_MAILER=log` y no salía nada. Se conserva porque explica
qué hace falta si hay que configurarlo de nuevo.

## Estado al 2 de agosto

`MAIL_MAILER=log`, tanto en `.env` como en `.env.example`. Con ese valor Laravel
**no envía nada**: escribe el mensaje completo en `storage/logs/laravel.log` y
devuelve éxito. Por eso nada falla de forma visible — y por eso tampoco llega
nada.

## Qué depende de esto

**1. Recuperar la contraseña.** Las rutas existen y funcionan
(`GET/POST forgot-password`, `GET/POST reset-password`), y `User` usa
`Notifiable`, así que el mecanismo está completo. Lo único que falta es el
transporte. Hoy, si alguien del CERSEU olvida su contraseña, el enlace de
recuperación acaba en un fichero de log del servidor: hace falta que alguien con
acceso por SSH lo saque de ahí, o restablecerla por consola.

**2. Avisos de solicitudes de diplomado.** `DiplomadoLeadController` envía a
`SiteSetting::contacto('admision')` cuando alguien rellena el formulario. El
envío va dentro de un `try/catch` que registra el fallo y **no interrumpe al
visitante** —correcto—, pero implica que un correo que no sale no se nota: el
usuario ve «tu solicitud fue registrada» igualmente.

Los leads **sí quedan guardados** en la tabla `diplomado_leads` y son visibles
en el panel (Solicitudes), así que no se pierde ninguno aunque el correo falle.
El aviso es una comodidad, no el registro.

## Qué hay que hacer

En el `.env` de **producción** (no en el repositorio):

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=cerseu.letras@unmsm.edu.pe
MAIL_PASSWORD=<contraseña de aplicación>
```

Todo eso **ya está puesto salvo la contraseña**.

### Gmail no acepta la contraseña de la cuenta

Desde 2022 Google no permite autenticar en SMTP con la contraseña normal. Hace
falta una **contraseña de aplicación**: 16 caracteres, generada aparte, y que
exige tener activada la verificación en dos pasos en la cuenta.

Si se usa la contraseña de la cuenta, Gmail responde:

```
535-5.7.8 Username and Password not accepted
```

Cómo generarla: entrar en la cuenta → *Seguridad* → activar *Verificación en 2
pasos* → *Contraseñas de aplicaciones* → crear una para «Correo». Se copia sin
espacios en `MAIL_PASSWORD`.

`MAIL_SCHEME` va en `smtp` (puerto 587, STARTTLS). Con `tls` Laravel 12 falla:
*«The "tls" scheme is not supported»*. Para el puerto 465 sería `smtps`.

### Si las contraseñas de aplicación «no están disponibles»

**Usar la contraseña de la cuenta no es una alternativa.** Google la rechaza en
SMTP desde mayo de 2022; no es configurable desde aquí.

Antes de descartarlas, comprobar lo más habitual: **la opción solo aparece si la
cuenta tiene la verificación en dos pasos activada**. Sin 2FA no está en el
menú, y es fácil concluir que el dominio la bloquea cuando no es así.

Si con 2FA activa sigue sin aparecer, la bloquea el administrador de Workspace
de `unmsm.edu.pe`. Opciones, de mejor a peor:

**1. Relé SMTP de Google** — `smtp-relay.gmail.com`, puerto 587. El
administrador autoriza la **IP del servidor** en *Apps → Gmail → Enrutamiento →
Servicio de relé SMTP*. Autentica por IP, así que **no hace falta contraseña**:

```
MAIL_HOST=smtp-relay.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
```

Es lo indicado para una cuenta institucional: nadie tiene que custodiar una
credencial y no se rompe si alguien cambia la contraseña del buzón.

**2. Servidor de correo propio de la universidad.** Conviene preguntarlo antes
que nada: si el área de sistemas facilita un `mail.unmsm.edu.pe` o similar,
funciona con usuario y contraseña normales — la restricción es de Google, no del
protocolo. Sería lo más rápido.

**3. Que el administrador habilite las contraseñas de aplicación** para esa
cuenta.

**4. Servicio transaccional** (Brevo, Mailgun, Resend). Solo cambia el bloque
`MAIL_*`, el código no se toca. Pero exige que el administrador añada **SPF y
DKIM** al DNS de `unmsm.edu.pe`, o los correos acaban en spam: también pasa por
él, así que no evita la gestión.

En los cuatro casos, tras cambiarlo: `php artisan config:cache` (producción
tiene la configuración en caché), `docker restart cerseuletras_queue` —el
worker de la cola es el que envía y conserva la configuración vieja hasta que
se reinicia— y `php artisan correo:probar`.

### El panel avisa de los correos que no salieron

Cada solicitud guarda si su aviso llegó a enviarse. Las que no, salen en
*Solicitudes* con la etiqueta **«Aviso no enviado»** —el motivo aparece al pasar
el ratón— y un botón para **reintentar el envío**. Así, en cuanto haya
transporte, se recuperan las que entraron mientras el correo no funcionaba sin
copiar nada a mano.

El modo `log` **no cuenta como enviado**: escribe en el log del servidor y no lo
recibe nadie, así que darlo por bueno en el panel sería engañoso.

El formulario público nunca se bloquea por esto. El envío tiene un límite de
10 segundos (`MAIL_TIMEOUT`); antes heredaba el del `php.ini`, normalmente 60, y
en un servidor con el puerto de salida cerrado eso acababa en un 500 para el
visitante aunque su solicitud sí se hubiera guardado.

### Mientras tanto no se pierde nada

Las solicitudes de diplomado **se guardan en la base y salen en el panel**
(Solicitudes) aunque el correo falle. El aviso es una comodidad, no el registro.
Lo que sí queda inutilizable hasta que haya transporte es **recuperar la
contraseña** del panel. No hay comando para eso; se hace con tinker, en el
servidor:

```bash
php artisan tinker
>>> $u = App\Models\User::where('email', 'correo@unmsm.edu.pe')->first();
>>> $u->password = Hash::make('la-nueva'); $u->save();
```

El enlace de recuperación que envía la web sigue existiendo y funcionando: lo
único que falta es el transporte que lo entregue.

**El remitente ya está puesto** en `.env` y `.env.example`:

```
MAIL_FROM_ADDRESS="cerseu.letras@unmsm.edu.pe"
MAIL_FROM_NAME="CERSEU Letras UNMSM"
```

Solo faltan las cuatro líneas de conexión de arriba, que son credenciales.

El remitente es un buzón real del dominio `unmsm.edu.pe`, que es lo que exigen
SPF y DKIM: antes estaba en `hello@example.com` —el valor de fábrica de
Laravel— y con eso los correos habrían acabado rechazados o en spam.

**Las solicitudes de información llegan a `cerseu.letras@unmsm.edu.pe`**,
que sale de Configuración → Contacto en el panel, no de un valor fijo en el
código. Cambiarlo ahí cambia el destino.

Tras rellenarlo:

```bash
php artisan config:clear
php artisan correo:probar                    # al correo de admisión
php artisan correo:probar tu@correo.pe       # o a donde prefieras
```

El comando envía una solicitud de ejemplo igual que la del formulario —sin
guardarla en la base—, avisa si sigue en modo `log` y, si el envío falla, lista
las causas habituales.

## Qué está ya comprobado

`tests/Feature/CorreoSolicitudTest.php` verifica en cada ejecución de la suite
que el aviso sale al enviar el formulario, que va al correo de admisión
configurado en el panel, que responder escribe al solicitante, que el remitente
es del dominio `unmsm.edu.pe` y que **la solicitud se guarda aunque el envío
falle**. Lo único que no puede comprobar un test es el transporte real: para eso
está `correo:probar`.

## Aviso sobre la caché de configuración

Si en producción se ejecuta `php artisan config:cache`, **hay que volver a
ejecutarlo cada vez que cambie el `.env`** o los valores viejos seguirán
activos. Esta caché ya causó un problema en este proyecto: dejó `APP_ENV`
fijado y una tanda de tests corrió contra la base de datos de desarrollo.

## Pendiente de decidir

- **Verificación de correo al crear usuarios.** Las rutas de verificación no
  están publicadas (los tests de Breeze que fallan son justo por eso). No hace
  falta mientras las cuentas las cree un administrador a mano.
