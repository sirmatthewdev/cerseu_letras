#!/bin/bash
# Días que le quedan al certificado TLS.
#
# La ruta se deduce de la ubicación del propio script en vez de venir escrita a
# mano: antes apuntaba a /home/posgrado/PosgradoLetras8, que solo existía en un
# servidor concreto y dejó de ser válida al renombrarse el proyecto.
# Se puede forzar otra con la variable CERT_PATH.
#
# Sale con 0 si quedan 30 días o más, 1 si no encuentra el certificado y 2 si
# quedan menos de 30: así un cron puede avisar solo cuando hace falta.
RAIZ=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
CERT_PATH="${CERT_PATH:-$RAIZ/docker/nginx/ssl_sectigo/fullchain.pem}"
DOMINIO="${DOMINIO:-cerseuletras.unmsm.edu.pe}"

if [ ! -f "$CERT_PATH" ]; then
    echo "❌ No se encontró el certificado en: $CERT_PATH"
    echo "   Indica otra ruta con: CERT_PATH=/ruta/fullchain.pem $0"
    exit 1
fi

EXPIRY_DATE=$(openssl x509 -in "$CERT_PATH" -noout -enddate | cut -d= -f2)
EXPIRY_EPOCH=$(date -d "$EXPIRY_DATE" +%s)
CURRENT_EPOCH=$(date +%s)
DAYS_LEFT=$(( (EXPIRY_EPOCH - CURRENT_EPOCH) / 86400 ))

echo "📅 Certificado SSL"
echo "   Dominio:        $DOMINIO"
echo "   Válido hasta:   $EXPIRY_DATE"
echo "   Días restantes: $DAYS_LEFT días"

if [ "$DAYS_LEFT" -lt 30 ]; then
    echo "⚠️  ADVERTENCIA: el certificado expira en menos de 30 días"
    echo "   Renueva antes de esa fecha."
    exit 2
fi
