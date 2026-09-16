# Documentación Técnica — CERSEU Letras UNMSM

Sitio del Centro de Responsabilidad Social y Extensión Universitaria de la
Facultad de Letras y Ciencias Humanas. Para instalación y Docker, ver el
[README de la raíz](../README.md).

## 1. Arquitectura del Sistema

### Visión General
El sistema está construido sobre el framework **Laravel** (v12.x), utilizando una arquitectura MVC (Modelo-Vista-Controlador) tradicional. El frontend utiliza **Blade** como motor de plantillas, potenciado con **Tailwind CSS** para los estilos y **Alpine.js** para la interactividad ligera en el cliente.

### Stack Tecnológico
- **Backend:**
  - Laravel Framework: `^12.0`
  - PHP: `^8.2`
- **Frontend:**
  - Tailwind CSS: `v3.x` (Estándar)
  - Alpine.js: `^3.4.2`
  - Vite: `^7.0.7`
- **Base de Datos:**
  - Motor: MySQL `8.0` (Recomendado/Estándar)
  - Driver: PDO MySQL (PHP `^8.2`)

### Entorno Docker

La imagen de PHP se construye desde `docker/php/Dockerfile`:

- **Base:** `php:8.2-fpm` (Debian)
- **Extensiones PHP:** `pdo_mysql`, `mbstring`, `exif`, `pcntl`, `bcmath`,
  `gd`, `zip`, `intl`, `opcache`
- **GD se compila con `--with-webp`** (además de freetype y jpeg). No es un
  detalle menor: sin esa bandera `imagewebp()` no existe, `OptimizadorImagen`
  falla y su plan B guarda la imagen subida tal cual —sin redimensionar ni
  comprimir—. El fallo es silencioso y solo se nota en el peso de las páginas.
- **Node.js:** 22.x, desde el repositorio de NodeSource
- **Composer:** 2.x, copiado de la imagen oficial
- **Usuario:** corre como `laravel`, no como root. Su UID/GID se fijan con los
  argumentos de construcción `UID` y `GID` (ver el `.env` de la raíz). En Linux
  deben coincidir con los del dueño del repositorio o el contenedor no podrá
  escribir en `storage/`.

MySQL 8.0 se construye desde `docker/mysql/Dockerfile`, que añade la
configuración de `my.cnf` (utf8mb4, zona horaria de Lima) y un script de
arranque que crea la base de pruebas `<base>_testing`.

#### Servicios y Puertos Expuestos
| Servicio | Puerto en el host | Descripción |
|----------|-------------------|-------------|
| **web**  | `80` y `443`      | Nginx. El 443 solo sirve con los certificados puestos; la capa de desarrollo usa el 80 sin TLS |
| **app**  | —                 | PHP-FPM 8.2, solo dentro de la red de Docker. La capa de desarrollo publica además el `5173` de Vite |
| **db**   | `3307` → `3306`   | MySQL 8.0. El puerto del host se cambia con `DB_PORT` en el `.env` de la raíz; dentro de la red siempre es el 3306 |

### Estructura de Directorios Clave
- `app/Filament`: el panel. Recursos, páginas y widgets; aquí vive la
  administración.
- `app/Http/Controllers`: lo poco que queda fuera del panel.
    - `Api/`: el contrato con el sitio público. Es por donde sale el contenido.
    - `VistaPreviaController`, `ExportacionDeSolicitudesController`: las dos
      cosas que no caben dentro de un recurso de Filament y viven en `/gestion`.
- `app/Models`: modelos Eloquent.
- `lang/`: el español del panel y de la sesión —validación, autenticación,
  contraseñas—. Sin estos archivos un formulario en blanco responde
  «validation.required», la clave en crudo.
- `resources/views`: lo que queda de Blade son la sesión, el perfil y los
  correos. Las vistas del panel las pone Filament.
- `routes/web.php`: solo el panel y la sesión. El sitio público no pasa por
  aquí.

Ya no hay `Controllers/Admin/` ni `resources/views/admin/`: eran el panel
anterior —19 controladores y 41 vistas— y se retiraron al alcanzar paridad
Filament.

---

## 2. Base de Datos

### Dónde no debe vivir el contenido

CERSEU nació forkeando el sitio de la Unidad de Posgrado, y durante meses
publicó cosas que no le correspondían: documentos de tesis y grados, un
proceso de admisión de maestrías, y una portada afirmando que sus cursos
tienen examen de admisión y entrevista. La causa no fue el fork en sí, sino
que el contenido estaba escrito **dentro del código**, y aparecía en tres
sitios distintos:

| Dónde | Ejemplo real | Por qué costó verlo |
|---|---|---|
| **Seeder** | `DocumentsSeeder` sembraba diez PDF de Posgrado | Visible, pero solo si lo abres |
| **Migración** | `..._create_cronograma_admision_tables` insertaba el cronograma de admisión de maestrías | Nadie busca contenido en una migración, y corre **antes** que los seeders |
| **Fallback de un controlador** | `AdminCronogramaAdmisionController::index()` creaba la fila con los títulos de Posgrado si no existía | Solo se dispara cuando la tabla está vacía |

El de la migración es el que más daño hizo: `ContenidoInicialSeeder` se retira
si ya existe un cronograma, así que corregir el JSON del seeder **no cambiaba
nada en una instalación nueva**. La corrección arreglaba las bases ya creadas
y dejaba intacto el problema para quien instalara de cero. Se detectó clonando
el repositorio en limpio y siguiendo el README, no revisando el código.

**La regla, entonces:** el contenido institucional —programas, cronogramas,
docentes, requisitos, textos— no se escribe en un seeder, ni en una migración,
ni como valor por defecto de un controlador. Las migraciones crean estructura;
los seeders, a lo sumo, el andamiaje mínimo para que el sitio arranque sin
pantallas rotas; el contenido real entra por el panel. Si al instalar de cero
aparece un texto que nadie escribió desde el panel, está mal por definición.

### Esquema Relacional
El sistema utiliza las siguientes tablas principales:

#### Tablas de Contenido
- **programas**: la oferta del CERSEU. Pese al nombre heredado, una fila es un
  taller, un curso o una especialización; los distingue la columna `grado`
  (`Taller` / `Curso` / `Especialización`).
    - Campos: `id`, `nombre`, `slug`, `grado`, `mencion`, `modalidad`,
      `sumilla`, `plan_estudios`, `inversion_economica`, `estado`
      (`publicado` / `proximamente` / `borrador`), `deleted_at`.
    - Duración: hay una columna por unidad —`horas_academicas`, `sesiones`,
      `modulos` y `duracion` (meses)— y cada tipo llena solo las suyas. Las
      demás quedan en blanco y no se muestran.
    - El enum `App\Models\TipoOferta` concentra lo único que cambia entre los
      tres tipos: rótulo, segmento de URL, valor de `grado` y —en `medidas()`—
      qué columnas de duración usa cada uno y con qué nombre se rotulan:
      **Taller** → `horas_academicas` («horas académicas»); **Curso** →
      `sesiones` («sesiones») y `horas_academicas`; **Especialización** →
      `modulos` («módulos») y `duracion` («meses»).
    - Añadir un cuarto tipo es añadir un `case` al enum: rutas, menús, filtros
      del panel y heros se generan recorriendo `TipoOferta::cases()`.
- **docentes**: Información de los profesores.
    - Campos: `id`, `slug`, `nombres`, `apellidos`, `grado`, `email`, `orcid`,
      `cti_vitae`, `linkedin`, `biografia`, `foto`, `lineas_investigacion`,
      `grupo_investigacion`, `estado`, `deleted_at`.
- **docente_programa**: Tabla pivote para la relación Muchos-a-Muchos entre Docentes y Programas.
- **testimonios**: testimonios de participantes. Se administran desde el
  panel; no vienen sembrados. La sección de la portada y `/testimonios` se
  ocultan solas mientras la tabla esté vacía.
    - Campos: `id`, `nombre`, `contenido`, `cargo`, `programa_id`, `imagen_path`, `published`, `orden`.
- **eventos**: eventos y noticias académicas.
    - Campos: `id`, `titulo`, `imagen`, `descripcion`, `fecha_inicio`,
      `fecha_fin`, `url`, `tipo_url`, `orden`, `activo`, `deleted_at`.
- **informativos**: enlaces a documentos y recursos, agrupados por `categoria`.
    - Campos: `id`, `categoria`, `titulo`, `tipo`, `url`, `orden`, `deleted_at`.
- **cronogramas / cronograma_items**: el calendario de `/cronograma`. La
  cabecera lleva `code`, `title`, `description`, `effective_date` e
  `is_active`; las filas van en `cronograma_items` (`section`,
  `is_section_heading`, `actividad`, `fecha_text`, `orden`), de modo que una
  fila puede ser un encabezado de bloque o una actividad con su fecha.
    - **Se siembra vacío a propósito.** Antes traía trece filas heredadas del
      proceso de maestrías y doctorados —examen de admisión, entrevista
      personal, matrícula de ingresantes— con fechas concretas que el CERSEU
      nunca tuvo. La vista muestra su estado vacío hasta que la Unidad cargue
      las suyas.
- **cronograma_admisiones / cronograma_admision_pasos**: el bloque «Cómo
  inscribirte» de la portada, independiente del anterior. La cabecera lleva
  `eyebrow`, `titulo`, `boton_texto`, `boton_url` e `is_visible`; cada paso,
  `titulo`, `detalle`, `icono` (de `CronogramaAdmision::ICONOS`), `publico`,
  `fecha_inicio`/`fecha_fin` opcionales, `orden`, `destacado` e `is_visible`.
  La portada oculta la sección entera si no queda ningún paso visible.

#### Tablas de Admisión y Solicitudes
- **admision_settings**: una fila por tipo de oferta (`tipo` = `taller` |
  `curso` | `especializacion`, con índice único). Guarda el contenido completo de
  `/{tipo}/admision`: hero, pasos, requisitos, pago, resultados y contacto.
- **admision_cronograma_items**: convocatorias de cada módulo
  (`admision_setting_id`, `programa`, `convocatoria`, fechas, `estado`). Aquí
  vive la programación anual: un mismo curso puede dictarse varias veces.
- **leads**: solicitudes de información del formulario público, con `tipo` para
  saber de qué módulo vienen y `aviso_enviado_en` / `aviso_error` para dejar
  constancia de si el correo de aviso salió.

#### Tablas de Configuración y Sistema
- **users**: Usuarios del sistema (Administradores).
    - Roles: definidos por columna `role` (ej. 'admin').
    - **No hay alta pública ni verificación de correo.** Las cuentas se crean
      desde el panel, y las rutas `register` y `verify-email` no se
      registran. Los controladores que Breeze dejó para eso se retiraron: eran
      inalcanzables y arrastraban una referencia a una ruta inexistente.
      Quedan los de inicio de sesión, cierre de sesión, cambio y recuperación
      de contraseña, que sí se usan.
- **site_settings**: configuración global. Es una tabla de **una sola fila**
  —el modelo lo impide en `creating`— con una columna por ajuste: logo,
  favicon, correos por rol, teléfono y `anexo`, redes, y los heros de portada
  y de cada tipo de oferta
  (`{talleres|cursos|especializaciones}_hero_{titulo|texto|claim|imagen}`).
- **content_pages / content_sections**: contenido editable de `/tramites`,
  `/admision` y `/nosotros`. `ContentPage::GRUPOS` define si una página se
  divide en pestañas; `/tramites` ya no las usa.
- **documents**: documentos PDF publicables (`type`, `title`,
  `original_name`, `url`, `published`). Se siembra vacía: las diez filas
  que traía apuntaban a ficheros que nunca existieron y hablaban de tesis
  y grados académicos. Se cargan desde el panel, en «Documentos».
- **directorio_cerseu**: directorio de contacto. A la espera del equipo del
  CERSEU está vacía y su enlace no aparece en el menú.

---

## 3. Módulos del Sistema

### Panel Administrativo (`/panel`)

El panel es **Filament**. El acceso lo decide `User::canAccessPanel()`: hace
falta rol de administrador **y** cuenta activa — las dos cosas, o dar de baja a
alguien no le cerraría la puerta.

Hubo un panel anterior escrito a mano en `/admin` (19 controladores, 41 vistas
Blade). Se retiró cuando este alcanzó paridad. Lo que no cabía dentro de un
recurso de Filament vive ahora bajo `/gestion`: la vista previa de borradores y
la exportación de solicitudes a CSV.

#### Funcionalidades clave

1.  **Escritorio**: el widget `ResumenDelSitio` con el estado del contenido.
2.  **Oferta**: talleres, cursos y especializaciones en un solo recurso. El
    tipo se elige en «Grado», y publicar o pasar a borrador es una acción de la
    propia lista.
3.  **Docentes**: catálogo, y sus perfiles académicos (ORCID, CTI Vitae,
    LinkedIn). La asignación a programas se hace desde la ficha del programa,
    en la pestaña «Docentes»: es una relación con datos propios —quién coordina,
    con qué denominación, con qué rol y en qué orden— y por eso es un *relation
    manager* y no un selector.
4.  **Configuración del sitio**: identidad visual, contacto, heros por módulo y
    ajustes del anuncio de portada.
5.  **Cronogramas**: dos pantallas distintas y fáciles de confundir. El recurso
    «Cronogramas» edita la tabla de `/cronograma`; la página «Cronograma de
    admisión» edita el bloque «Cómo inscribirte» de la portada.
6.  **Admisión por módulo**: una fila por tipo de oferta, cada una con sus
    requisitos, pasos y cronograma.
7.  **Solicitudes**: solo lectura —las crea el visitante—, con filtros,
    exportación a CSV y reenvío del aviso por correo cuando el envío falló.
8.  **Papelera**: lo borrado en cualquier sección se recupera desde aquí.

`PanelHumoTest` recorre **todas** las pantallas del panel con un admin
autenticado y falla si alguna pasa de 400. Los recursos se enumeran
preguntándole al panel, no a mano, así que uno nuevo entra solo en el recorrido.

Abre listado, creación **y edición con un registro real**. Lo último importa:
había una prueba que solo abría listado y creación, y aun así la pantalla de
edición de la admisión llevaba tiempo devolviendo un 500 —su título salía de un
atributo casteado a enum, y Filament exige una cadena—. Sin registro no hay
título que resolver, así que nada lo veía.

### Frontend Público

**No lo sirve Laravel.** Es un sitio estático en Astro (`sitio/`) que se
construye contra `routes/api.php` y que Nginx entrega como ficheros. Laravel
solo responde HTML en `/panel`, `/login`, `/profile` y `/gestion`. Publicar
desde el panel encola un trabajo que pide reconstruirlo.

Lo que sigue describe **lo que el sitio pinta con esta API**, no plantillas de
este repositorio.

#### Secciones Principales:
1.  **Inicio**: hero, indicadores, oferta destacada con filtro por tipo.
2.  **Talleres** (`/talleres`), **Cursos** (`/cursos`) y **Especializaciones**
    (`/especializaciones`): una sola plantilla para los tres, que sale de la
    API y no de una lista escrita a mano —añadir un cuarto tipo en el enum lo
    publica sin tocar el sitio—. Cada uno trae listado, ficha (`/{tipo}/{slug}`),
    admisión (`/{tipo}/admision`) y formulario de solicitud.
3.  **Admisión** (`/admision`): reparte hacia el proceso de cada tipo, que
    es donde vive el de verdad. No describe un proceso propio: las tarjetas
    se generan recorriendo `TipoOferta::cases()` y solo el texto de entrada
    es editable. Antes detallaba aquí el proceso de maestrías y doctorados
    heredado de la Unidad de Posgrado.
4.  **Trámites**: constancias y certificados. La página se arma con tantas
    secciones como se carguen desde el panel, sin número fijo.
5.  **Plana Docente**: buscador y perfiles.
6.  **Nosotros**: misión, visión, valores y autoridades.

Las rutas anteriores `/programas` y `/diplomados` responden con 301 hacia
`/cursos` y `/talleres`, para no romper enlaces ya publicados.

---

## 4. Requisitos de Instalación (Hardware/Software)

### Software Requerido
- **Servidor Web:** Apache o Nginx
- **Lenguaje:** PHP >= 8.2
    - Extensiones: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO,
      Tokenizer, XML, Intl, Zip, Exif y **GD compilada con soporte WebP**.
    - Comprueba el WebP con `php -r 'var_dump(function_exists("imagewebp"));'`.
      Si sale `false`, las imágenes que se suban desde el panel se guardarán
      sin optimizar y nadie avisará.
- **Base de Datos:** MySQL 8.0 (es la que usan los contenedores; 5.7 o
  MariaDB 10.3+ también sirven)
- **Composer:** v2.x
- **Node.js:** v18+ y NPM (para compilación de assets)
- **Docker (Entorno de Desarrollo):**
    - Docker Engine: `^24.0` (o superior)
    - Docker Compose: `v2.x`

### Hardware Recomendado (Servidor)
- **CPU:** 2 vCores mínimo.
- **RAM:** 4GB mínimo (recomendado 8GB para procesos de compilación y caché).
- **Almacenamiento:** 40GB SSD (considerando almacenamiento de documentos e imágenes).

---

## 5. Guía de Despliegue (Deployment)

1.  **Clonar Repositorio:**
    ```bash
    git clone [url-repo]
    cd cerseuletras
    ```

2.  **Instalar Dependencias PHP:**
    ```bash
    composer install --optimize-autoloader --no-dev
    ```

3.  **Configurar Entorno:**
    - Copiar `.env.docker` a `.env` (trae ya los valores del proyecto; el
      `.env.example` es la plantilla genérica de Laravel)
    - Configurar credenciales de BD (`DB_DATABASE`, `DB_USERNAME`, etc.) y las
      de `MAIL_*`
    - Si despliegas con Docker, esas credenciales de BD deben coincidir con las
      del `.env` de la raíz del repositorio, que es el que lee Compose
    - Generar key: `php artisan key:generate`

4.  **Base de Datos:**
    ```bash
    php artisan migrate --force
    php artisan db:seed --class=DatabaseSeeder # (Solo si es primera instalación)
    ```
    Varias tablas se siembran **vacías a propósito** —`documents`,
    `cronograma_items`, `directorio_cerseu`, `testimonios`— porque lo que
    contenían era de la Unidad de Posgrado y no hay equivalente del CERSEU
    que poner. Las secciones que las muestran traen su estado vacío; no es
    que la instalación haya fallado.

    Los seeders dejan el sitio utilizable: menú, contenido de `/tramites`,
    `/admision` y `/nosotros`, ajustes del sitio y la programación 2026 del
    CERSEU. Son idempotentes en lo que importa —no pisan contenido ya editado
    desde el panel— pero conviene no reejecutarlos sobre una instalación viva
    sin mirar antes qué hace cada uno.

5.  **Instalar Dependencias Frontend y Compilar:**
    ```bash
    npm install
    npm run build
    ```

6.  **Optimización (Producción):**
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link
    ```
