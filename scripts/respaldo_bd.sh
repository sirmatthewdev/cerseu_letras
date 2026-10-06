#!/bin/bash
# Copia diaria de la base de datos, pensada para el cron del usuario.
#
# El volcado lo hace el contenedor `db` y no `php artisan db:respaldar`: la
# imagen de PHP no trae `mysqldump`, y la de MySQL sí, con la versión exacta
# del servidor. Se vuelca como root para que entren también las rutinas y los
# eventos; la contraseña la pone el propio contenedor desde su entorno, así que
# no pasa por este script ni por la lista de procesos.
#
# Deja `cerseuletras_AAAA-MM-DD_HHMMSS.sql.gz` en DESTINO y borra los que
# tengan más de DIAS días. Sale con 1 si el volcado falla o sale vacío, y
# entonces no deja fichero: un respaldo de 0 bytes es peor que ninguno, porque
# parece que lo hay.
#
# Esto NO saca la copia del servidor. Hay que sincronizar DESTINO con otra
# máquina: si se pierde esta, se pierden la base y sus copias a la vez.
set -euo pipefail

RAIZ=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
DESTINO="${DESTINO:-$HOME/respaldos/cerseuletras}"
DIAS="${DIAS:-14}"

mkdir -p "$DESTINO"
chmod 700 "$DESTINO"

FICHERO="$DESTINO/cerseuletras_$(date +%Y-%m-%d_%H%M%S).sql.gz"
PARCIAL="$FICHERO.parcial"
trap 'rm -f "$PARCIAL"' EXIT

cd "$RAIZ"
umask 077
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot \
    --single-transaction --routines --events --triggers "$MYSQL_DATABASE"' \
    | gzip > "$PARCIAL"

# mysqldump termina con «-- Dump completed» solo si llegó al final.
if ! zcat "$PARCIAL" | tail -n 1 | grep -q 'Dump completed'; then
    echo "❌ El volcado no se completó; no se guarda." >&2
    exit 1
fi

mv "$PARCIAL" "$FICHERO"
find "$DESTINO" -name 'cerseuletras_*.sql.gz' -mtime +"$DIAS" -delete

echo "✅ $(date '+%F %T') $FICHERO ($(du -h "$FICHERO" | cut -f1))"
