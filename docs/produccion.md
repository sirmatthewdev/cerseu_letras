# Puesta en producción desde cero

De una VM Linux recién entregada a `https://cerseuletras.unmsm.edu.pe` sirviendo,
con los servicios levantándose solos en cada arranque de la máquina.

Hay tres documentos de instalación y conviene no confundirlos:

| Documento | Para qué |
|---|---|
| [README](../README.md#instalación-rápida) | Montarlo en la máquina de quien lo desarrolla |
| **Este** | **Montarlo en el servidor, desde cero, para abrirlo al público** |
| [cerseuletras/docs/despliegue.md](../cerseuletras/docs/despliegue.md) | Actualizar una instalación que **ya existe** y viene del sitio anterior |

Todo lo que sigue se ejecuta **en la VM**, por SSH, desde la raíz del
repositorio clonado. Ningún comando lleva la capa `-f docker-compose.dev.yml`:
esa capa enciende el servidor de Vite, publica puertos de desarrollo y monta la
configuración de nginx **sin TLS**. En un servidor no se usa nunca.

---

## 0. Antes de tocar nada

### La máquina

| | Mínimo | Por qué |
|---|---|---|
| CPU | 2 vCPU | La construcción del sitio son 80 páginas de Astro |
| RAM | 4 GB | MySQL, Redis, PHP-FPM, el trabajador de colas y Node a la vez |
| Disco | 20 GB | Las imágenes de Docker pesan ~2,5 GB; el resto son la base, las imágenes subidas desde el panel y dos árboles de `node_modules` |

### Lo que hay que tener a mano

Nada de esto está en el repositorio, y sin ello la instalación se queda a
medias:

- **El certificado TLS** del dominio. En esta VM los oficiales ya están en la
  máquina: el paso 3 es localizarlos y colocarlos, no conseguirlos. Sin ellos el
  contenedor de nginx no arranca.
- **El registro DNS** del dominio apuntando a la IP de la VM, y los puertos
  **80** y **443** abiertos en el cortafuegos de la máquina y en el de la red
  institucional.
- **La contraseña de aplicación del buzón** del CERSEU para el envío de correo
  (ver [correo.md](../cerseuletras/docs/correo.md)). No es la contraseña de la
  cuenta de Google: es una contraseña de aplicación.
- **La contraseña del primer administrador**, decidida por la Unidad. Se pone en
  el `.env` antes de sembrar.

### Docker

Docker **Engine** con el plugin de Compose, no Docker Desktop. En Ubuntu o
Debian:

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"   # cerrar la sesión SSH y volver a entrar
docker compose version            # debe responder v2.x
```

Y lo que de verdad decide que esto sea permanente:

```bash
sudo systemctl enable --now docker
systemctl is-enabled docker       # -> enabled
```

**Las políticas de reinicio de los contenedores no valen nada si el demonio no
arranca con la máquina.** `restart: unless-stopped` es una instrucción *para
Docker*: si nadie levanta Docker tras el reinicio, no hay quien la cumpla. En
las instalaciones por paquete suele quedar habilitado solo; comprobarlo cuesta
un comando y es el único punto de esta guía que no se puede arreglar después sin
que el sitio haya estado caído.

---

## 1. Clonar

```bash
git clone https://github.com/sirmatthewdev/cerseu_letras.git /opt/cerseuletras
cd /opt/cerseuletras
```

La ruta da igual —todos los montajes del compose son relativos al
`docker-compose.yml`—, pero **el dueño de los ficheros importa**: en Linux el
bind mount conserva el propietario real, y el contenedor de PHP corre como un
usuario no-root. Clona con el mismo usuario con el que vas a operar, y no con
`sudo`, o el paso 2 tendrá que arreglarlo.

Si el repositorio es privado, Git pedirá credenciales: un *token de acceso
personal* como contraseña, o una clave de despliegue si prefieres SSH. La
contraseña de la cuenta de GitHub ya no sirve.

---

## 2. Los dos `.env`

Hay **dos**, los leen programas distintos, y confundirlos es el fallo más
repetido de esta instalación:

| Fichero | Lo lee | Para qué |
|---|---|---|
| `.env` (raíz) | Docker Compose | Crear la base de datos y su usuario, el dominio de nginx, el token de reconstrucción |
| `cerseuletras/.env` | Laravel | Conectarse a esa base, el correo, las claves de la aplicación |

Compose **solo** mira el que está junto al `docker-compose.yml`. Si pones la
contraseña únicamente en el de Laravel, MySQL se crea con la de plantilla y la
aplicación no consigue entrar.

```bash
cp .env.example .env
cp cerseuletras/.env.docker cerseuletras/.env
id -u    # -> el UID que va en el .env de la raíz
id -g    # -> el GID
```

### En `.env` (raíz)

```env
DB_DATABASE=cerseuletras
DB_USERNAME=cerseu_user
DB_PASSWORD=<una contraseña larga y nueva>
DB_ROOT_PASSWORD=<otra distinta>

UID=1000                 # el id -u de arriba
GID=1000                 # el id -g de arriba

NGINX_SERVER_NAMES=cerseuletras.unmsm.edu.pe www.cerseuletras.unmsm.edu.pe
CERSEU_SITE=https://cerseuletras.unmsm.edu.pe
CERSEU_BUILD_TOKEN=<un secreto largo, el mismo que en el otro .env>
```

`NGINX_SERVER_NAMES` lleva **todos** los dominios separados por espacios, y deja
fuera `www.` si no existe ese registro DNS: nginx no arranca pidiendo un
certificado para un nombre que el certificado no cubre. El dominio no está
escrito en ninguna `.conf` —son plantillas que el entrypoint de nginx rellena al
arrancar—, así que cambiar de dominio es cambiar esta variable.

`CERSEU_SITE` es otra cosa y hace falta igual: de ahí salen el sitemap y las URL
canónicas que Astro escribe al construir. Sin barra final.

### En `cerseuletras/.env`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cerseuletras.unmsm.edu.pe
LOG_LEVEL=warning

DB_DATABASE=cerseuletras          # los tres, IDÉNTICOS
DB_USERNAME=cerseu_user           # a los del .env de la raíz
DB_PASSWORD=<la misma de arriba>

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=<el buzón>
MAIL_PASSWORD=<la contraseña de aplicación>

ADMIN_PASSWORD=<la del primer administrador>
CERSEU_BUILD_TOKEN=<el mismo secreto del .env de la raíz>
```

`APP_DEBUG=false` no es una preferencia: con el depurador encendido, cualquier
excepción imprime en pantalla el `.env` entero, contraseña de base de datos
incluida.

`ADMIN_PASSWORD` hay que ponerla **antes** de sembrar (paso 5). Si se deja
vacía, el seeder genera una de 20 caracteres y la imprime una sola vez; si nadie
la copia en ese momento, se pierde y hay que ponerla a mano. Y va en el `.env`
antes de `config:cache`, porque a partir de ese comando Laravel deja de leer el
fichero.

`CERSEU_BUILD_TOKEN` tiene que coincidir en los dos ficheros. Es el secreto con
el que Laravel pide una reconstrucción del sitio cuando alguien publica desde el
panel: si no coincide, la petición se rechaza y el sitio se queda con el
contenido de la última construcción manual, sin que nadie vea un error.

Ninguno de los dos ficheros se versiona.

---

## 3. El certificado

Los oficiales **ya están en la VM**. El trabajo aquí no es conseguirlos: es
localizarlos, comprobar que son los del dominio, dejarlos donde la plantilla de
nginx los busca y asegurarse de que nadie más está usando los puertos.

### 3.1 Localizarlos

```bash
sudo ls -l /etc/ssl/certs /etc/ssl/private /etc/pki/tls 2>/dev/null
sudo find /etc -xdev -name '*.crt' -o -name '*.pem' -o -name '*.ca-bundle' 2>/dev/null
```

Y si en la máquina ya había un servidor web sirviendo con ellos, él mismo dice
qué ficheros usa —que es la respuesta más fiable:

```bash
sudo grep -rn 'ssl_certificate' /etc/nginx/ 2>/dev/null
sudo grep -rni 'SSLCertificate' /etc/apache2/ /etc/httpd/ 2>/dev/null
```

### 3.2 Comprobar que son los correctos

Antes de copiar nada. Un certificado que no cubre el dominio, o una clave que no
es la suya, se manifiesta como «nginx no arranca» y se tarda un rato en atribuir
la causa:

```bash
sudo openssl x509 -in <el.crt> -noout -subject -ext subjectAltName -dates

# ¿La clave es la de este certificado? Las dos líneas deben coincidir.
sudo openssl x509 -in <el.crt> -noout -pubkey | openssl md5
sudo openssl pkey -in <la.key> -pubout | openssl md5
```

El `subjectAltName` tiene que cubrir **todos** los nombres de
`NGINX_SERVER_NAMES` (paso 2). Si el certificado no incluye `www.`, quita ese
nombre de la variable en lugar de dejar a nginx pidiendo un certificado que no
existe.

Y si la clave está protegida con contraseña, nginx la pedirá por consola al
arrancar y nadie se la va a teclear: `sudo openssl pkey -in <la.key> -noout`; si
la pide, se quita con `openssl rsa -in <la.key> -out privkey.pem`.

### 3.3 Dejarlos en su sitio

Dos ficheros, con estos nombres exactos:

```bash
sudo install -m 644 -o "$(id -u)" -g "$(id -g)" <el.crt>  docker/nginx/ssl_sectigo/fullchain.pem
sudo install -m 600 -o "$(id -u)" -g "$(id -g)" <la.key>  docker/nginx/ssl_sectigo/privkey.pem
```

La carpeta viene con el clon, y viene por una razón menor que conviene saber:
Git no versiona carpetas vacías, así que lleva dentro un `LEEME.md` para
existir. Sin ese fichero no estaría, y el bind mount del compose la crearía como
`root` al levantar — con lo que el error ya no sería «falta el certificado» sino
«no puedo escribir aquí».

**`fullchain.pem` es la cadena completa**: el certificado del servidor seguido de
los intermedios. Los certificados institucionales suelen entregarse como `.crt`
más un `.ca-bundle` aparte, y entonces `fullchain.pem` no es ninguno de los dos,
sino la concatenación —**el del servidor primero**:

```bash
cat cerseuletras_unmsm_edu_pe.crt bundle.ca-bundle > docker/nginx/ssl_sectigo/fullchain.pem
grep -c 'BEGIN CERTIFICATE' docker/nginx/ssl_sectigo/fullchain.pem   # 2 o más
```

Si sale `1`, falta la cadena. Con solo el certificado del servidor los
navegadores de escritorio suelen apañárselas y los clientes de móvil y las APIs
no: el fallo aparece en unos sitios y no en otros, que es la forma más incómoda
de que aparezca.

Más detalle, en la propia carpeta:
[docker/nginx/ssl_sectigo/LEEME.md](../docker/nginx/ssl_sectigo/LEEME.md).

### 3.4 Copiar y no montar la ruta del sistema

Se copian, aunque haya la tentación de montar `/etc/ssl/...` directamente en el
contenedor. Montar la ruta del sistema entrega la clave privada a un contenedor
y ata el despliegue a la organización de ficheros de *esta* máquina; copiar deja
el certificado donde el proyecto lo declara, con el dueño correcto, y la
renovación es volver a copiar y recargar:

```bash
docker compose exec web nginx -t && docker compose exec web nginx -s reload
```

`nginx -t` primero, siempre: valida la configuración y los certificados sin
tocar el servidor en marcha. Un `reload` con un `.pem` mal copiado tira el
sitio; un `-t` que falla no hace nada.

### 3.5 Quién tiene los puertos 80 y 443

Si esos certificados los estaba usando un nginx o un Apache **del sistema**, ese
servidor tiene los puertos, y el contenedor `web` no podrá arrancar: el fallo es
`address already in use` en el paso 4. Hay que apartarlo antes:

```bash
sudo ss -ltnp '( sport = :80 or sport = :443 )'
sudo systemctl disable --now nginx apache2 2>/dev/null
```

`disable` y no solo `stop`: con `stop` a secas, el servicio del sistema vuelve en
el siguiente arranque de la máquina y se adelanta a Docker. El sitio quedaría
caído tras un reinicio, que es justo lo que el paso 7 viene a evitar.

### Si en algún momento hay que emitir uno con Let's Encrypt

No es el caso aquí, pero conviene que esté escrito porque tiene un orden que
sorprende: la validación por `--webroot` necesita que algo sirva el puerto 80, y
nginx no arranca sin el certificado que se va a validar. Se rompe el círculo
emitiendo el primero en modo autónomo, con nginx todavía parado:

```bash
sudo certbot certonly --standalone -d cerseuletras.unmsm.edu.pe
sudo cp /etc/letsencrypt/live/cerseuletras.unmsm.edu.pe/fullchain.pem docker/nginx/ssl_sectigo/
sudo cp /etc/letsencrypt/live/cerseuletras.unmsm.edu.pe/privkey.pem   docker/nginx/ssl_sectigo/
```

Las renovaciones posteriores ya pueden ir por `--webroot`: la plantilla deja
abierta `/.well-known/acme-challenge/` sobre `cerseuletras/public`. Hay que
copiar los `.pem` otra vez y recargar nginx, como en 3.4.

---

## 4. Construir las imágenes y levantar

```bash
docker compose build
docker compose up -d
```

`build` antes que `up` y no al revés: el `UID`/`GID` del paso 2 entra como
argumento **de construcción**, así que ponerlos después de haber construido no
cambia nada. Si los editas más tarde, hay que repetir el `build`.

Sin la capa de desarrollo son seis servicios:

```
app     PHP-FPM          la aplicación y el panel
web     nginx            TLS, el sitio estático y el reparto hacia PHP
db      MySQL 8          la base
redis   Redis 7          caché, sesiones y la cola
queue   PHP              el trabajador de la cola
build   Node 22          construye el sitio público cuando se publica
```

```bash
docker compose ps        # los seis, Up
```

`db` no sale todavía aceptando conexiones aunque el contenedor esté arriba:
MySQL tarda unos segundos en inicializar la base la primera vez y `depends_on`
no espera a que esté listo. Si el paso siguiente responde «Connection refused»,
es esto; se repite el comando.

---

## 5. Instalar la aplicación

En este orden. Los cinco primeros comandos son la instalación; las cachés del
final congelan la configuración y hay que dejarlas para el final.

```bash
# 1. Dependencias de PHP, sin las de desarrollo y con el autoload compilado
docker compose run --rm app composer install --no-dev --optimize-autoloader

# 2. La clave de la aplicación (cifra sesiones y cookies; se genera una vez)
docker compose run --rm app php artisan key:generate

# 3. Esquema y contenido inicial.  --force: en producción `migrate` pide una
#    confirmación por teclado que en `run` no hay quien conteste
docker compose run --rm app php artisan migrate --force --seed

# 4. Enlace de storage, para las imágenes que se suban desde el panel
docker compose run --rm app php artisan storage:link

# 5. Los assets del panel (Filament + Vite)
docker compose run --rm app npm ci
docker compose run --rm app npm run build
```

`npm ci` y no `npm install`: instala exactamente lo que dice el
`package-lock.json`. En un servidor no se quiere resolver versiones, se quiere
reproducir una instalación conocida.

`--seed` solo la primera vez. Deja el sitio utilizable desde el primer arranque:
el menú, los textos de `/nosotros`, `/tramites` y `/admision`, los ajustes del
sitio, la programación 2026 del CERSEU —39 cursos con sus docentes y 47
convocatorias— y el usuario `admin@cerseuletras.unmsm.edu.pe` con la contraseña
de `ADMIN_PASSWORD`.

**Anota lo que imprima el seeder sobre el administrador.** Si `ADMIN_PASSWORD`
estaba vacía, ahí va la contraseña generada y no vuelve a mostrarse.

Si en una reinstalación la cuenta ya existe, el seeder lo dice y **no le toca la
contraseña**.

Hay partes que quedan vacías **a propósito**, y no es que la instalación fallara:
los documentos descargables, el cronograma de `/cronograma`, el directorio y los
testimonios. Lo que había en esas tablas era de la Unidad de Posgrado y no hay
equivalente del CERSEU que poner, así que se cargan desde el panel. Esas páginas
traen su propio estado vacío.

### Y ahora las cachés

```bash
docker compose run --rm app php artisan config:cache
docker compose run --rm app php artisan route:cache
docker compose run --rm app php artisan view:cache
```

A partir de `config:cache`, **Laravel deja de leer el `.env`**. Cada cambio
posterior en ese fichero obliga a repetir el comando, y cuando algo no responde
a la configuración que se acaba de poner, `php artisan config:clear` es lo
primero que hay que probar.

Por eso van aquí y no antes: sembrar necesita leer `ADMIN_PASSWORD` del `.env`.

---

## 6. Construir el sitio público

```bash
docker compose run --rm build sh -c "npm ci && npm run build"
```

Esto llena `sitio/dist/`, que es lo que sirve nginx. **Sin este paso el dominio
responde 404** aunque `/panel` y `/api` funcionen: nginx sirve ficheros y
todavía no hay ninguno.

El servicio se llama **`build`**. `astro` solo existe en la capa de desarrollo:
en el servidor, un `docker compose run --rm astro …` contesta `no such service:
astro`. El sitio se construye contra `http://web/api/v1` —el nombre del servicio
de nginx dentro de la red de Docker—, que ya viene puesto en el compose; no
sirve `localhost`, porque ahí dentro sería este mismo contenedor.

La construcción tarda un par de minutos y son 80 páginas. Si falla pidiendo
contenido, la causa casi siempre es que el paso 5 no llegó a sembrar.

---

## 7. Que sobreviva a los reinicios

Los seis servicios llevan `restart: unless-stopped`:

```bash
docker compose config | grep -E '^  [a-z]+:|restart:'
```

Hasta hace poco **`app` era el único sin política**, y resultaba ser el peor sitio
para no tenerla: tras reiniciar la VM volvían nginx, la base, Redis, las colas y
el build —y no PHP—, de modo que el sitio parecía levantado y cada petición al
panel y a la API devolvía 502. Un fallo que no da la cara hasta que alguien
intenta entrar.

`unless-stopped` y no `always` a propósito: si alguien para un servicio a mano
para operar sobre él, no quiere que vuelva solo en el siguiente arranque. La
contrapartida es la que hay que recordar: **un servicio parado a mano sigue
parado tras el reinicio**. Al terminar cualquier mantenimiento, `docker compose
up -d`.

Y `docker compose down` elimina los contenedores: ninguna política resucita algo
que ya no existe. Se vuelve con `docker compose up -d` (los datos están en
volúmenes nombrados y no se pierden).

No hace falta una unidad de systemd propia. La combinación de un demonio Docker
habilitado (paso 0) y las políticas de los seis servicios es exactamente lo que
haría esa unidad, con una pieza menos que mantener.

**Compruébalo de verdad, antes de anunciar el sitio:**

```bash
sudo reboot
# y al volver a entrar, sin ejecutar nada más:
docker compose ps
```

Los seis tienen que estar `Up` sin que nadie los levante. Es la única
comprobación que dice algo sobre el próximo corte de luz.

---

## 8. Comprobar que está en pie

```bash
for r in / /login /panel /api/v1/sitio; do
  printf '%-16s ' "$r"
  curl -s -o /dev/null -w '%{http_code}\n' "https://cerseuletras.unmsm.edu.pe$r"
done
```

| Ruta | Esperado | Qué es |
|---|---|---|
| `/` | 200 | El `dist/` de Astro |
| `/login` | 200 | La entrada al panel, en español |
| `/panel` | 302 → `/login` | Filament, tras autenticar |
| `/api/v1/sitio` | 200 JSON | El contrato que consume el sitio |

`/up` —la ruta de salud que define Laravel— **responde 404 y es lo normal**: el
reparto de nginx no la manda a PHP, así que la contesta el sitio estático. Para
una sonda externa, usa `/api/v1/sitio`.

El resto:

```bash
docker compose logs --tail=30 queue    # el trabajador, esperando trabajos
docker compose logs --tail=30 web      # nginx, sin errores de certificado
docker compose run --rm app php artisan correo:probar   # un correo de prueba
```

Y la vuelta completa de publicación, que es la parte que más fácilmente queda
rota sin que se note: publica cualquier cambio desde el panel y mira
`docker compose logs -f build`. Tiene que aparecer la reconstrucción. Si no
aparece nada, el `CERSEU_BUILD_TOKEN` de los dos `.env` no coincide.

---

## 9. Lo que la Unidad tiene que cargar desde el panel

No es trabajo de instalación, pero el sitio no está terminado sin ello:
documentos descargables, cronograma, directorio, testimonios, y las tarifas
reales de los programas —las sembradas son genéricas por grado—. El favicon y
los datos de contacto también salen del panel: lo que se sube ahí manda sobre lo
que trae el repositorio.

---

## 10. Operación

### Actualizar

```bash
git pull
docker compose build                       # solo si cambió un Dockerfile
docker compose up -d
docker compose run --rm app composer install --no-dev --optimize-autoloader
docker compose run --rm app php artisan migrate --force
docker compose run --rm app npm ci
docker compose run --rm app npm run build
docker compose run --rm app php artisan config:cache
docker compose run --rm app php artisan route:cache
docker compose run --rm app php artisan view:cache
docker compose run --rm build sh -c "npm ci && npm run build"
```

Sin `--seed`: los seeders de contenido no son todos idempotentes y el contenido
ya vive en la base, editado desde el panel.

### Copias de seguridad

```bash
docker compose run --rm app php artisan db:respaldar
```

Deja el volcado en `storage/app/` (o donde diga `--destino`). **Guárdalo fuera
del servidor**: una copia que vive en la máquina que se puede perder no es una
copia. Una entrada diaria en el cron del usuario, y el fichero sincronizado a
otro sitio.

### El certificado

```bash
scripts/check_ssl_expiry.sh      # días que le quedan; sirve para un cron
```

Un certificado caducado no degrada el sitio: lo cierra, con una pantalla de
advertencia del navegador. Vigilarlo cuesta una línea de cron.

### Cambiar la contraseña del administrador

Desde el propio panel, en el perfil del usuario, o por consola:

```bash
docker compose run --rm app php artisan tinker
```

```php
$u = App\Models\User::where('email', 'admin@cerseuletras.unmsm.edu.pe')->first();
// El modelo castea `password` a `hashed`: se cifra al guardar, sin `Hash::make`.
$u->password = 'la-nueva-contrasena';
$u->save();
```

Para dar acceso al panel a una cuenta creada después:

```bash
docker compose run --rm app php artisan usuario:admin correo@unmsm.edu.pe
```

### Logs y entrar a un contenedor

```bash
docker compose logs -f app
docker compose exec app bash
tail -f cerseuletras/storage/logs/laravel.log
```

---

## 11. Seguridad: lo que hay que saber de esta instalación

- **MySQL está publicado solo en `127.0.0.1`.** Publicar un puerto en Docker
  abre un agujero en el cortafuegos del sistema —las reglas de `ufw` se evalúan
  después de las de Docker y no lo tapan—, así que un mapeo `3307:3306` habría
  dejado la base escuchando en la IP pública con la contraseña del `.env` como
  única defensa. Desde la propia máquina sigue siendo `127.0.0.1:3307` para un
  cliente o un volcado; los servicios hablan entre ellos por `db:3306` y no
  necesitan nada de esto.
- **La contraseña del administrador estuvo escrita en el repositorio** y era
  «admin123». Ya no está —sale de `ADMIN_PASSWORD`—, pero **sigue en el
  historial de Git**: cualquier entorno sembrado con una versión anterior de
  `UserSeeder.php` tiene esa cuenta abierta. Si traes datos de una instalación
  vieja, cambia esa contraseña antes de exponer nada.
- **Los dos `.env` no se versionan**, y las contraseñas de plantilla de
  `.env.example` no valen para nada expuesto: están ahí para que la instalación
  arranque en una máquina de desarrollo.
- **`CERSEU_VISTA_PREVIA_TOKEN` vacío desactiva la vista previa de borradores**,
  y eso es lo correcto mientras nadie la necesite: es preferible que no funcione
  a que un descuido deje los borradores a la vista de cualquiera.

---

## 12. Si algo no arranca

| Síntoma | Causa casi siempre |
|---|---|
| `web` en bucle de reinicio | Falta `fullchain.pem` o `privkey.pem`, o el certificado no cubre un nombre de `NGINX_SERVER_NAMES`. `docker compose logs web` |
| `address already in use` al levantar | Un nginx o Apache del sistema tiene el 80 y el 443. `sudo ss -ltnp '( sport = :80 or sport = :443 )'` y paso 3.5 |
| El navegador avisa de cadena incompleta, y el móvil no entra | `fullchain.pem` lleva solo el certificado del servidor: falta concatenar el `.ca-bundle` (paso 3.3) |
| El dominio responde 404, pero `/panel` funciona | `sitio/dist/` está vacío: falta el paso 6 |
| 502 en `/panel` y `/api`, el sitio se ve | `app` no está arriba. `docker compose ps` y `docker compose logs app` |
| «Connection refused» a la base | MySQL aún inicializaba. Repetir el comando |
| «Access denied for user» | Los `DB_*` de los dos `.env` no coinciden. Si la base ya se creó con los viejos, cambiar la contraseña dentro de MySQL o rehacer el volumen `db-data` |
| No puede escribir en `storage/` | El `UID`/`GID` del `.env` no es el dueño de los ficheros. Corregirlo y **reconstruir** (es un argumento de construcción) |
| La configuración nueva no tiene efecto | `config:cache`. `php artisan config:clear` |
| Publicar en el panel no actualiza el sitio | El `CERSEU_BUILD_TOKEN` no coincide en los dos `.env`. `docker compose logs build` |
| Los avisos de solicitudes no llegan | `MAIL_*`, o el trabajador caído. Quedan registrados en `leads.aviso_error`; `php artisan correo:probar` |

Más detalle en [Solución de Problemas](../README.md#solución-de-problemas) del
README.
