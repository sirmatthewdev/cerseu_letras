# CERSEU Letras — Laravel 12 + Astro, con Docker

Sitio del **Centro de Responsabilidad Social y Extensión Universitaria** de la
Facultad de Letras y Ciencias Humanas de la UNMSM.

El sitio son **dos piezas**, y conviene tenerlo claro desde el principio:

| | Qué es | Dónde vive |
|---|---|---|
| **Laravel** | El panel y la API de contenido | `cerseuletras/` |
| **Astro** | El sitio público, ficheros estáticos | `sitio/` |

Astro se construye contra la API y Nginx entrega el resultado. Laravel solo
responde HTML en `/panel`, `/login` y `/profile`; todo lo demás son ficheros.
Publicar desde el panel encola un trabajo que pide reconstruir el sitio, así
que un cambio de contenido no exige tocar nada a mano.

El CERSEU ofrece **tres tipos** de formación abierta a toda la comunidad. Cada
uno se anuncia con la unidad que le es propia:

| Tipo | Se mide en |
|---|---|
| **Talleres** | horas académicas |
| **Cursos** | sesiones distribuidas y horas académicas |
| **Especializaciones** | módulos y meses |

Los tres funcionan igual: listado, ficha, página de admisión y formulario de
solicitud de información, cada uno con su propio cronograma. El contenido se
administra desde el panel en `/panel`.

> El sitio nació como portal de la Unidad de Posgrado y conserva de aquel origen
> el nombre de la tabla `programas`, donde ahora viven los tres tipos: los
> distingue la columna `grado`. Las URLs anteriores `/programas` y `/diplomados`
> redirigen con 301 a `/cursos` y `/talleres`, para no romper enlaces ya
> publicados.

## Requisitos

- Docker Desktop instalado
- Docker Compose v2+

## Estructura del Proyecto

```
├── docker-compose.yml
├── docker/
│   ├── php/
│   │   ├── Dockerfile
│   │   └── php.ini
│   ├── mysql/
│   │   ├── Dockerfile
│   │   ├── my.cnf
│   │   └── docker-entrypoint-initdb.d/
│   └── nginx/
│       ├── templates/
│       │   ├── default.conf.template      ← sin TLS (desarrollo)
│       │   └── default-ssl.conf.template  ← con TLS (producción)
│       └── compression.conf
├── docker-compose.dev.yml   ← capa de desarrollo (sin TLS, debug on)
├── .env.example             ← variables de Compose (BD y su usuario)
├── .gitattributes           ← fuerza LF en los ficheros que ejecuta Linux
├── scripts/
│   ├── check_ssl_expiry.sh      ← días que le quedan al certificado
│   └── migrar_bd_a_cerseu.sh    ← vuelca posgradoletras → cerseuletras
├── cerseuletras/            ← Laravel 12: panel y API (ver su propio README)
│   ├── app/
│   ├── lang/                ← español del panel y de la sesión
│   ├── routes/api.php       ← el contrato con el sitio
│   ├── routes/web.php       ← solo el panel y la sesión
│   └── ...
└── sitio/                   ← Astro: el sitio público
    ├── public/              ← se copia tal cual a la raíz (el favicon)
    ├── src/pages/           ← una página, una ruta
    ├── src/components/      ← lo que se repite entre páginas
    ├── src/styles/global.css ← paleta, tipografías y utilidades de marca
    ├── src/lib/api.ts       ← el único sitio que habla con la API
    ├── src/lib/             ← y las reglas compartidas (ver más abajo)
    ├── e2e/                 ← pruebas de navegador (Playwright)
    ├── herramientas/        ← servicio que reconstruye al publicar
    └── dist/                ← lo que sirve Nginx (no versionado)
```

## Instalación Rápida

### 1. Configurar los dos archivos .env

Hay **dos**, y es fácil confundirlos porque los lee gente distinta:

| Fichero | Lo lee | Para qué |
|---|---|---|
| `.env` (raíz) | Docker Compose | Crear la base de datos y su usuario |
| `cerseuletras/.env` | Laravel | Conectarse a esa base, correo, etc. |

Compose solo mira el `.env` que está junto al `docker-compose.yml`. Si pones la
contraseña únicamente en el de Laravel, MySQL se creará con la de plantilla y
la aplicación no podrá entrar.

```bash
cp .env.example .env
cp cerseuletras/.env.docker cerseuletras/.env
```

Ahora edita los dos y **haz que coincidan** en `DB_DATABASE`, `DB_USERNAME` y
`DB_PASSWORD`. En el de la raíz va además `DB_ROOT_PASSWORD`; en el de Laravel,
las credenciales de `MAIL_*` si vas a enviar correo. Ninguno de los dos `.env`
se versiona.

**Si vas a servir en un dominio** y no en `localhost`, pon también
`NGINX_SERVER_NAMES` en el `.env` de la raíz —el dominio no está escrito en
ninguna conf, ver [Desplegar en una VM Linux](#desplegar-en-una-vm-linux).

**Si esto es tu máquina de desarrollo**, cambia también estas dos líneas en
`cerseuletras/.env`:

```env
APP_ENV=local
APP_DEBUG=true
```

`.env.docker` trae los valores de producción (`production` y `false`), que es lo
correcto para un servidor pero estorba en local por dos motivos: los errores
salen como un 500 en blanco, y —más molesto— `php artisan migrate` detecta el
entorno de producción y pide una confirmación por teclado que en
`docker compose run` no hay quien conteste, así que el paso 3 se cancela solo
con un «Command cancelled» y la base se queda vacía. Si prefieres dejarlo en
`production`, añade `--force` a los comandos de migración.

### 2. Construir e iniciar los contenedores

**Producción** (nginx con TLS en los puertos 80 y 443):

```bash
docker compose build
docker compose up -d
```

Necesita los certificados en `docker/nginx/ssl_sectigo/` (`fullchain.pem` y
`privkey.pem`). No están en el repositorio —son secretos— y sin ellos el
contenedor `web` no arranca.

**Desarrollo** (sin TLS, por el puerto 80):

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

Sirve por el puerto 80 sin certificados, apunta `APP_URL` a `http://localhost`
y publica el 5173 para `npm run dev`.

Lo que **no** hace es encender el modo depuración: eso sale de `APP_ENV` y
`APP_DEBUG` en `cerseuletras/.env` (paso 1). Están ahí y no en el compose a
propósito —exportarlas como variables del contenedor las mete en `$_SERVER`,
donde PHPUnit no puede sobrescribirlas ni con `force="true"`, y la suite acaba
corriendo contra la base de desarrollo en lugar de la de pruebas—.

El fichero se llama `docker-compose.dev.yml` y no `docker-compose.override.yml`
a propósito: con ese nombre Compose lo aplicaría solo, y el servidor acabaría
con el modo depuración encendido tras un `git pull`.

La capa saca además `vendor/` y `node_modules/` del bind mount a volúmenes
nombrados. **En Windows esto no es opcional**: sobre el bind mount de Docker
Desktop, `readdir()` trunca los directorios grandes —en la carpeta de iconos de
FontAwesome, `scandir()` ve los 1758 SVG pero `FilesystemIterator` devuelve
926— y la mitad de los iconos deja de existir para la aplicación, con lo que
cualquier página que use uno revienta con «Unable to locate a class or view for
component». Como contrapartida, `composer install` y `npm install` hay que
ejecutarlos dentro del contenedor —que es lo que indican los pasos de abajo— y
el editor del host no ve esas dos carpetas.

### 3. Instalar dependencias y configurar Laravel

En los comandos de abajo, si levantaste con la capa de desarrollo, añade los
mismos `-f` a cada `docker compose run`.

```bash
# Dependencias de PHP
docker compose run --rm app composer install

# Key de la aplicación
docker compose run --rm app php artisan key:generate

# Migraciones y contenido inicial.
# En el primer arranque MySQL tarda unos segundos en aceptar conexiones y
# `depends_on` no espera a que esté listo: si sale «Connection refused»,
# repite el comando.
docker compose run --rm app php artisan migrate --seed

# Enlace de storage (imágenes subidas desde el panel)
docker compose run --rm app php artisan storage:link

# Assets del panel
docker compose run --rm app npm install
docker compose run --rm app npm run build
```

Y el sitio público, que se construye contra la API que acabas de sembrar:

```bash
docker compose run --rm astro npm install
docker compose run --rm -e CERSEU_API=http://web/api/v1 astro npm run build
```

`http://web` es el nombre del servicio de Nginx dentro de la red de Docker.
No sirve `localhost`: ahí dentro sería el propio contenedor de Astro.

Sin este paso `sitio/dist/` está vacío y el dominio responde 404 —Nginx sirve
ficheros y todavía no hay ninguno—, aunque `/panel` y `/api` funcionen.

`--seed` deja el sitio utilizable desde el primer arranque: menú, textos de
`/nosotros`, `/tramites` y `/admision`, ajustes del sitio y la programación
2026 del CERSEU (39 cursos con sus docentes y 47 convocatorias). Sin él, esas
secciones salen en blanco porque su contenido es administrable y no vive en las
vistas.

Si al instalar de cero aparece contenido institucional que nadie escribió
desde el panel, es un fallo: el contenido no debe vivir en el código. Está
explicado, con los tres sitios donde se coló contenido de Posgrado, en
[«Dónde no debe vivir el contenido»](cerseuletras/README.md#dónde-no-debe-vivir-el-contenido).

Aun con `--seed` hay partes que salen vacías **a propósito**: los documentos
descargables, el cronograma de `/cronograma`, el directorio y los
testimonios. Lo que había en esas tablas era de la Unidad de Posgrado y no
hay equivalente del CERSEU que poner, así que se cargan desde el panel. Las
páginas afectadas traen su estado vacío: no es que la instalación fallara.

### 4. Permisos de escritura

Laravel escribe en `storage/` y `bootstrap/cache`. El contenedor corre como un
usuario no-root, y **en Linux el bind mount conserva el propietario real de los
ficheros**: si el usuario del host no coincide con el del contenedor, la
aplicación no puede escribir y revienta al primer log.

Antes de construir, pon tus identificadores en el `.env` de la raíz:

```bash
id -u    # -> UID
id -g    # -> GID
```

Con `UID=1000` y `GID=1000` —lo habitual en el primer usuario de una VM— no
hay nada que cambiar. En Windows y macOS da igual: Docker Desktop no traslada
los propietarios.

Si aún así aparece un error de permisos:

```bash
docker compose run --rm app chmod -R 775 storage bootstrap/cache
```

### 5. Acceder a la aplicación

| | Dirección | Qué es |
|---|---|---|
| Sitio | [http://localhost](http://localhost) | El `dist/` de Astro servido por Nginx |
| Panel | [http://localhost/panel](http://localhost/panel) | Laravel + Filament |
| API | [http://localhost/api/v1/sitio](http://localhost/api/v1/sitio) | El contrato entre ambos |
| Sitio en desarrollo | [http://localhost:4321](http://localhost:4321) | Servidor de Astro con recarga en caliente |

Los dos últimos solo en la capa de desarrollo.

El de :4321 recarga al guardar y es con el que se trabaja; el del puerto 80 es
lo que se publica, y solo cambia al construir. Cuando algo se vea distinto en
uno y otro, el que manda es el del 80.

Los seeders crean un administrador con contraseña de desarrollo —la verás
impresa al sembrar—: **cámbiala antes de exponer el sitio**, porque está
escrita en `database/seeders/UserSeeder.php`.

## Desplegar en una VM Linux

Lo anterior está verificado clonando el repositorio en limpio y siguiendo estos
pasos uno a uno. Al llevarlo a una VM hay cuatro diferencias:

1. **`UID`/`GID`** (paso 4). Es el fallo más común y no se manifiesta en
   Windows, porque allí Docker Desktop presenta todo el bind mount como `root`
   con permisos 777.

2. **Los volúmenes de `vendor/` y `node_modules/`** de la capa de desarrollo
   existen para esquivar un fallo de Docker Desktop en Windows, donde
   `readdir()` trunca los directorios grandes. En Linux no hacen falta, pero
   tampoco estorban: dejarlos evita tener dos configuraciones distintas.

3. **TLS**. La configuración de producción espera `fullchain.pem` y
   `privkey.pem` en `docker/nginx/ssl_sectigo/`, emitidos para
   `cerseuletras.unmsm.edu.pe`, y el DNS apuntando a la máquina. Sin eso, el
   contenedor `web` no arranca; usa la capa de desarrollo mientras tanto.

   El dominio **no** está escrito en ninguna conf: se pasa en
   `NGINX_SERVER_NAMES`, en el `.env` de la raíz. Las confs de
   `docker/nginx/templates/` son plantillas que el entrypoint de nginx
   rellena al arrancar, así que levantar el sistema en otro dominio es
   cambiar una variable, no editar código.

   ```env
   NGINX_SERVER_NAMES=otra-unidad.unmsm.edu.pe www.otra-unidad.unmsm.edu.pe
   ```

   Solo se sustituyen las variables cuyo nombre empieza por `NGINX_` —lo fija
   `NGINX_ENVSUBST_FILTER`—; sin ese filtro `envsubst` se llevaría por delante
   `$server_name`, `$request_uri` y `$fastcgi_script_name`, y nginx no
   arrancaría.

   El dominio del sitio va aparte, en `CERSEU_SITE`: de ahí salen el sitemap
   y las URL canónicas, que Astro necesita al construir.

   ```env
   CERSEU_SITE=https://otra-unidad.unmsm.edu.pe
   ```

4. **Reconstruir al publicar**. El servicio `build` recibe la petición que
   encola Laravel cuando alguien publica desde el panel. Comparten un token
   —`CERSEU_BUILD_TOKEN`— que hay que poner en los dos `.env`. Sin él, el
   trabajo se descarta en silencio y el sitio se queda con el contenido de la
   última construcción manual.

### Antes de abrirlo al público

Los pasos de «Instalación Rápida» dejan el sitio funcionando, pero en modo
desarrollo. Para un servidor hay que añadir:

```bash
# Dependencias sin las de desarrollo, y autoload optimizado
docker compose run --rm app composer install --no-dev --optimize-autoloader

# Assets del panel compilados para producción (no `npm run dev`)
docker compose run --rm app npm run build

# El sitio público, contra la API ya sembrada
docker compose run --rm -e CERSEU_API=http://web/api/v1 astro npm run build

# Migraciones sin la confirmación interactiva
docker compose run --rm app php artisan migrate --force

# Cachés de configuración, rutas y vistas
docker compose run --rm app php artisan config:cache
docker compose run --rm app php artisan route:cache
docker compose run --rm app php artisan view:cache
```

Ojo con `config:cache`: a partir de ahí Laravel deja de leer el `.env` y usa la
caché. Cada cambio en el `.env` obliga a repetirlo, y si algo deja de responder
a la configuración, `php artisan config:clear` es lo primero que hay que probar.

Y revisar en `cerseuletras/.env`:

- `APP_ENV=production` y `APP_DEBUG=false`. Con el depurador encendido, una
  excepción imprime en pantalla el `.env` entero, contraseña de base de datos
  incluida.
- `APP_KEY` generada (`php artisan key:generate`).
- `APP_URL` con el dominio real y `https`.
- `MAIL_*` con el buzón del CERSEU. Sin esto, los avisos de las solicitudes de
  información no salen y quedan registrados en `leads.aviso_error`.
- Las contraseñas de base de datos: que no sean las de plantilla, y que
  coincidan con las del `.env` de la raíz.

- `ADMIN_PASSWORD` con la contraseña del administrador inicial, **antes** de
  sembrar. Si se deja vacía, el seeder genera una y la imprime una sola vez: si
  no la anotas en ese momento, se pierde y hay que ponerla a mano.

Los seeders crean `admin@cerseuletras.unmsm.edu.pe`. La contraseña ya no está
escrita en el repositorio —lo estuvo, y era «admin123»: en un repositorio, una
contraseña escrita es una contraseña publicada—. Si el usuario ya existe, el
seeder no se la toca.

Para cambiarla después, desde el propio panel en el perfil del usuario, o por
consola:

```bash
docker compose run --rm app php artisan tinker
```

```php
$u = App\Models\User::where('email', 'admin@cerseuletras.unmsm.edu.pe')->first();
// El modelo castea `password` a `hashed`: se cifra al guardar, sin `Hash::make`.
$u->password = 'la-nueva-contrasena';
$u->save();
```

Para vigilar el certificado, `scripts/check_ssl_expiry.sh` dice los días que le
quedan; sirve para un cron. Si vas a traerte los datos del sitio anterior,
`scripts/migrar_bd_a_cerseu.sh` vuelca `posgradoletras` a `cerseuletras` sin
tocar la base de origen.

## Desarrollo sin Docker

El servidor de desarrollo del host no alcanza al contenedor de MySQL —el host
`db` solo resuelve dentro de Docker—, así que en local se usa SQLite:

```bash
cd cerseuletras
composer install && npm install && npm run build
php artisan migrate:fresh --seed
php artisan serve
```

Con `DB_CONNECTION=sqlite` y `DB_DATABASE` apuntando a un fichero `.sqlite`
en el `.env`.

Composer resuelve contra **PHP 8.2**, el del contenedor, y no contra el del
host: está fijado en `composer.json` (`config.platform`) y anotado en el
`composer.lock`. El `vendor/` es un bind mount —la misma carpeta la usan el
host, `app` y el trabajador de colas—, así que instalar con la versión del host
produciría un árbol que el contenedor no puede ejecutar. Sin ese anclaje, un
host con PHP 8.5 ni siquiera llega a instalar: `composer install` se detiene en
`openspout/openspout`, que pide `~8.2 || ~8.3 || ~8.4`, y deja el `vendor/` sin
Filament —y la aplicación entera caída con «Class "Filament\PanelProvider" not
found».

**La suite se corre dentro del contenedor, no en el host.** Sobre PHP 8.5
fallan dos pruebas del optimizador de imágenes —`OptimizacionImagenesTest` y
`PanelSubidaDeImagenesTest`— y solo en la corrida completa; por separado pasan.
No es un fallo del código: es el PHP con el que se ejecutan. Dentro de `app`, y
por tanto sobre el 8.2 de la imagen, la suite está verde.

## Comandos Útiles

### Artisan / Composer / NPM

```bash
docker compose run --rm app php artisan <comando>
docker compose run --rm app composer <comando>
docker compose run --rm app npm <comando>
```

### Pruebas

```bash
# Laravel: el panel, la API y las reglas de contenido
docker compose run --rm app php artisan test

# Los componentes del panel (Vitest)
docker compose run --rm app npm test

# El sitio: las reglas compartidas de `src/lib` (Vitest)
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm astro npm test

# El sitio, en un navegador de verdad (Playwright).
# Corre contra Nginx, así que hay que construir antes.
docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile e2e run --rm e2e
```

**Las cuatro pasan enteras.** Si algo falla, lo has roto tú: no hay fallos
heredados que haya que aprender a ignorar, y ese es justamente el motivo de
mantenerlas en verde.

La de navegador apunta a Nginx y no al servidor de desarrollo a propósito: lo
que se publica es el `dist/`, y hay comportamiento que solo existe ahí —los
301 heredados, la página 404, las cabeceras de caché—.

Hasta agosto de 2026 la suite de PHP terminaba con ocho fallos permanentes,
todos del andamiaje que Laravel Breeze deja al instalarse. Se resolvieron
mirando uno por uno en vez de silenciarlos:

- Las pruebas de **registro público** y **verificación de correo** se
  eliminaron. Comprobaban rutas que este sitio no sirve —no hay alta pública,
  los usuarios se crean desde el panel—, así que no cubrían nada.
- La de **inicio de sesión** afirmaba una redirección a `route('dashboard')`,
  que aquí no existe. Ahora comprueba lo que de verdad pasa: quien se
  autentica acaba en el panel, porque es lo único para lo que hay sesión.
- La de **confirmación de contraseña** destapó un fallo de verdad:
  `ConfirmablePasswordController` redirigía también a `route('dashboard')` y
  lanzaba `RouteNotFoundException`, o sea un 500.
- El **ExampleTest** de Laravel venía con `RefreshDatabase` comentado, así que
  pedía la portada sin base de datos. Se retiró al migrar el sitio: la portada
  ya no la sirve Laravel.

### Ver logs

```bash
docker compose logs -f app
docker compose logs -f web
docker compose logs -f db
```

### Entrar al contenedor

```bash
docker compose exec app bash
```

### Detener contenedores

```bash
docker compose down
```

### Detener y eliminar volúmenes (borra datos de MySQL)

```bash
docker compose down -v
```

## Servicios

| Servicio | Puerto | Descripción |
|---|---|---|
| web | 80 y 443 | Nginx. Sirve el `dist/` de Astro y pasa a PHP solo `/panel`, `/api`, `/gestion`, la sesión y los ficheros subidos |
| app | — | PHP-FPM 8.2 (interno, sin puerto publicado) |
| db | 3307 → 3306 | MySQL 8.0 (`DB_PORT` cambia el puerto del host) |
| redis | — | Caché, sesiones y colas |
| queue | — | Procesa la cola: avisos de solicitud y reconstrucción del sitio |
| astro | 4321 | Servidor de desarrollo del sitio (solo en la capa de desarrollo) |
| build | 4322 | Recibe la petición de reconstruir cuando se publica desde el panel |
| e2e | — | Playwright. Solo con `--profile e2e` |

El reparto entre el sitio y Laravel está en `docker/nginx/sitio.conf`, en un
solo fichero incluido por las dos plantillas —HTTP y HTTPS— para que no puedan
divergir. Lo que no esté en esa lista es sitio estático: añadir una página a
Astro no exige tocar Nginx.

## Base de Datos

Los valores salen de `.env`; los de abajo son los que trae `.env.docker` como
plantilla.

- **Host:** `db` (desde contenedores) / `localhost` (desde el host)
- **Puerto:** 3306 dentro de la red de Docker, 3307 desde el host
- **Base de datos:** `cerseuletras`
- **Usuario:** `cerseu_user`
- **Contraseña:** la que pongas en `DB_PASSWORD`

## Identidad visual

- **Azul institucional:** `#143B63`, con su escala (`unmsm-azul`, `-light`,
  `-dark`, `-soft`). **Dorado UNMSM:** `#B6A350` y `#C9AA36`.
- La paleta está declarada **dos veces, a propósito**: en
  `cerseuletras/tailwind.config.js` para el panel y en
  `sitio/src/styles/global.css` para el sitio. El sitio no depende del árbol de
  Laravel para construirse, y eso incluye sus colores.
- `--color-fondo-hondo` (`#0F1B26`) es el oscuro con el que abre la portada y
  sigue la banda de cifras. Lo comparten el hero, esa banda y los degradados de
  la fotografía: si uno cambia y otro no, aparece una línea horizontal a media
  página que nadie sabe de dónde sale.
- El rojo se reserva para lo semántico: errores de validación, botones de
  eliminar, iconos de PDF y la marca de YouTube.
- El logo se sirve desde `public/images/logo-cerseu.webp`. Va **sin fondo y con
  trazo oscuro**: el navbar y el pie le aplican `brightness-0 invert` para
  pintarlo de blanco sobre fondo oscuro. Un logo con fondo sólido se vería como
  un rectángulo blanco macizo bajo ese filtro.
- El favicon vive en `sitio/public/` y no se le pide a Laravel: el navegador lo
  busca en la raíz del dominio antes de ejecutar nada. Si la Unidad sube uno
  desde Configuración, ese manda.

## Convenciones del sitio

Cinco reglas que no se deducen leyendo una plantilla suelta. Cada una está
argumentada en el archivo que la implementa; esto es solo el índice.

- **Los iconos nombran la acción, no la dirección** (`src/lib/iconos.ts`). Nada
  de flechas genéricas: el destino decide el icono, porque la mitad de esas URL
  las escribe la Unidad desde el panel y elegirlo a mano deja el icono
  desfasado el día que alguien cambie el enlace.
- **Nada se oculta desde CSS, nunca** (`src/lib/animacion.ts`). El HTML llega
  con todo visible y el desvelado al hacer scroll solo toca lo que empieza
  fuera de la pantalla. Es la regla que este proyecto aprendió a la mala: dos
  veces se llegó a una sección en blanco por revelar lo que nunca debió
  esconderse.
- **Lo que sigue al ratón se marca con `data-sigue-raton`** (el script está en
  `src/pages/index.astro`). El elemento recibe `--raton-h` y `--raton-v`, de −1
  a 1, y el CSS decide qué hacer con ellas. El script no sabe nada del diseño.
- **La API dice si una imagen es propia o es el respaldo** (`imagen_propia` en
  los programas, `foto_propia` en los docentes). Antes cada plantilla lo
  averiguaba buscando el nombre del archivo de respaldo dentro de la URL, y
  bastaba renombrarlo para romperlas todas a la vez sin que nada avisara.
- **Un estado vacío ofrece a dónde ir** (`src/components/EstadoVacio.astro`).
  Talleres y Especializaciones llevan meses sin oferta; quien entra ahí tiene
  que salir con un enlace, no con el pie de página.

## Solución de Problemas

### El contenedor `web` no arranca, o el sitio responde 404 en el dominio

Las confs de nginx son **plantillas**: viven en `docker/nginx/templates/` y el
entrypoint de la imagen las rellena al arrancar, escribiendo el resultado en
`/etc/nginx/conf.d/` dentro del contenedor. Eso implica dos cosas que
despistan la primera vez:

- Editar `/etc/nginx/conf.d/default.conf` dentro del contenedor **no sirve**:
  se regenera en cada arranque. Edita la plantilla y recrea el servicio.
- Si el sitio no responde en tu dominio, lo primero es mirar qué
  `server_name` acabó generándose:

```bash
docker compose exec web cat /etc/nginx/conf.d/default.conf | head -5
docker compose exec web nginx -t
docker compose logs web | tail -20
```

Si ahí aparece `server_name ;` vacío, es que `NGINX_SERVER_NAMES` no llegó al
contenedor: revísala en el `.env` de la raíz y recrea con
`docker compose up -d --force-recreate web`.

### Permisos de storage/logs

```bash
docker compose run --rm app chmod -R 775 storage bootstrap/cache
docker compose run --rm app chown -R laravel:www-data storage bootstrap/cache
```

### Limpiar caché

```bash
docker compose run --rm app php artisan cache:clear
docker compose run --rm app php artisan config:clear
docker compose run --rm app php artisan view:clear
```

### Recrear contenedores

```bash
docker compose down
docker compose build --no-cache
docker compose up -d
```
