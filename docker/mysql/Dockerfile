# Base de datos del CERSEU.
#
# Antes el servicio usaba `mysql:8.0` tal cual y dependía de que quien
# desplegara acertara con la configuración: el juego de caracteres se dejaba
# al de por defecto de la imagen, y la base y el usuario se creaban solo a
# partir de variables de entorno.
#
# Aquí queda encapsulado: la imagen trae su propia configuración de servidor y
# un script de arranque que crea la base con la intercalación correcta. El
# esquema NO se hornea a propósito —vive en las migraciones de Laravel, que son
# su fuente de verdad— así que tras levantar el contenedor toca:
#
#     docker compose run --rm app php artisan migrate --seed
FROM mysql:8.0

# utf8mb4 de punta a punta. El contenido del sitio va en español y con la
# intercalación por defecto de MySQL, ordenar o comparar textos con tildes y
# eñes da resultados inesperados.
COPY my.cnf /etc/mysql/conf.d/cerseu.cnf

# El directorio se ejecuta solo la primera vez, cuando el volumen está vacío:
# un contenedor recreado sobre datos existentes no lo vuelve a correr.
COPY docker-entrypoint-initdb.d/ /docker-entrypoint-initdb.d/

# Ejecutable a proposito: un .sh sin el bit de ejecucion el entrypoint lo carga
# con `source`, dentro de su propio proceso, y el `set -euo pipefail` del script
# se queda activo para el resto del arranque. En la primera instalacion eso
# tumbo MySQL una vez. Ejecutable, corre en un proceso aparte. Se fija aqui y no
# en git porque al clonar en Windows el bit de ejecucion se pierde.
RUN chmod 0555 /docker-entrypoint-initdb.d/*.sh

# La imagen base copia los ficheros con los permisos del contexto de build, y
# MySQL ignora en silencio cualquier .cnf que sea escribible por el grupo.
RUN chmod 0444 /etc/mysql/conf.d/cerseu.cnf

EXPOSE 3306
