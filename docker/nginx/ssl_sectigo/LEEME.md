# Aquí van los certificados

Dos ficheros, con estos nombres exactos:

```
docker/nginx/ssl_sectigo/
├── fullchain.pem    el certificado del servidor y los intermedios, en ese orden
└── privkey.pem      la clave privada
```

La plantilla de nginx los busca por ruta fija. **Si falta uno, nginx no
arranca** —no sirve a medias— y el contenedor `web` se queda en bucle de
reinicio; `docker compose logs web` lo dice en la primera línea.

**Este fichero existe para que la carpeta exista.** Git no versiona carpetas
vacías: sin él, un clon no tendría `ssl_sectigo/`, el bind mount del compose la
crearía como `root` al levantar, y entonces el fallo ya no es «falta el
certificado» sino «no puedo escribir aquí». El `.gitignore` ignora
`*.pem`, `*.crt` y `*.key` en todo el árbol, así que un certificado no se puede
commitear por descuido. **La clave privada no entra en Git ni en un adjunto de
correo**; si alguna vez entra, lo que toca no es borrar el fichero: es pedir que
reemitan el certificado.

## Si el certificado institucional viene como `.crt` + `.ca-bundle`

Es lo normal en los que emite la universidad, y `fullchain.pem` no es ninguno de
los dos: es la concatenación, **el del servidor primero**.

```bash
cat cerseuletras_unmsm_edu_pe.crt bundle.ca-bundle > fullchain.pem
grep -c 'BEGIN CERTIFICATE' fullchain.pem      # 2 o más; con 1 falta la cadena
```

Con solo el certificado del servidor, los navegadores de escritorio suelen
apañárselas —completan la cadena por su cuenta— y los clientes de móvil y las
APIs no. El fallo aparece en unos sitios y no en otros, que es la forma más
incómoda de que aparezca.

## Comprobar antes de levantar

```bash
# ¿Es de este dominio, y hasta cuándo vale?
openssl x509 -in fullchain.pem -noout -subject -ext subjectAltName -dates

# ¿La clave es la de este certificado? Las dos líneas deben coincidir.
openssl x509 -in fullchain.pem -noout -pubkey | openssl md5
openssl pkey -in privkey.pem -pubout | openssl md5

# ¿La clave tiene contraseña? Si esto la pide, nginx también la pedirá
# al arrancar, y nadie se la va a teclear: hay que quitarla con
#   openssl rsa -in privkey.pem -out privkey.pem
openssl pkey -in privkey.pem -noout
```

El `subjectAltName` tiene que cubrir **todos** los nombres de
`NGINX_SERVER_NAMES`. Si el certificado no incluye `www.`, quita ese nombre de
la variable en lugar de dejar a nginx pidiendo un certificado que no existe.

## Permisos

```bash
chmod 644 fullchain.pem
chmod 600 privkey.pem
```

El proceso maestro de nginx lee los dos como `root` dentro del contenedor, así
que `600` en la clave no estorba y es lo que debe estar.

Lo demás —de dónde sacarlos, cómo renovarlos y qué vigilar— está en
[docs/produccion.md](../../../docs/produccion.md#3-el-certificado).
