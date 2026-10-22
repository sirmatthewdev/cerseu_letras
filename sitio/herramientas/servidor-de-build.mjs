/**
 * Servicio de reconstrucción del sitio.
 *
 * Escucha una petición de Laravel y ejecuta `astro build`. Existe porque al
 * separar el frontend, publicar dejó de ser instantáneo: el panel guarda en la
 * base y el sitio estático sigue mostrando lo anterior hasta que alguien
 * reconstruye. Esto cierra ese ciclo.
 *
 * Tres decisiones que no son obvias:
 *
 * 1. Va por HTTP y no vigilando un fichero compartido. Cuando el build pase a
 *    CI —que es donde debe acabar—, Laravel seguirá haciendo la misma llamada
 *    y solo cambia a quién. Con un fichero habría que reescribir las dos
 *    puntas.
 *
 * 2. Exige un token. Un endpoint que lanza un proceso pesado sin autenticar es
 *    una forma cómoda de tumbar el servidor desde fuera.
 *
 * 3. Las peticiones que llegan mientras hay un build en marcha NO encolan otro
 *    build cada una: marcan que hace falta repetirlo al terminar. Un editor
 *    que guarda diez cosas seguidas provoca dos builds, no diez.
 *
 * 4. No construye sobre `dist/`. Astro vacía el directorio de salida al
 *    empezar, y `dist/` es lo que sirve Nginx: cada publicación dejaba el
 *    sitio entero en 404 los segundos que duraba el build —medido: cinco—, y
 *    un build fallido lo dejaba roto hasta el siguiente. Ahora construye en
 *    `dist-nuevo/` y, solo si termina bien, mueve los ficheros a `dist/` (ver
 *    `publicar`).
 */
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { mkdir, readdir, readFile, rename, rm, rmdir, stat } from 'node:fs/promises';
import path from 'node:path';

const PUERTO = Number(process.env.PUERTO_BUILD ?? 4322);
const TOKEN = process.env.CERSEU_BUILD_TOKEN ?? '';

/**
 * Vista previa de borradores.
 *
 * Mismo `astro build`, con dos diferencias: lleva el token que hace que la API
 * sirva también los borradores, y escribe en otro directorio. Lo segundo es lo
 * importante — `dist/` es lo que Nginx publica, así que un borrador no puede
 * pasar por ahí ni un instante.
 */
const TOKEN_VISTA_PREVIA = process.env.CERSEU_VISTA_PREVIA_TOKEN ?? '';
const DIR_VISTA_PREVIA = path.resolve('/app', 'dist-vista-previa');

const DIR_PUBLICADO = path.resolve('/app', 'dist');
const DIR_NUEVO = path.resolve('/app', 'dist-nuevo');

/**
 * Los ficheros con hash que deja de usar un build se guardan unos días: quien
 * cargó la página justo antes de publicar todavía los pide, y borrarlos al
 * momento le rompería los estilos o el JavaScript a media visita.
 */
const DIAS_CONSERVANDO_ASSETS = 7;

let construyendo = false;
let repetirAlTerminar = false;
let ultimo = { estado: 'sin ejecutar', terminado: null, duracionMs: null, salida: null };
let ultimaVistaPrevia = { estado: 'sin ejecutar', terminado: null, duracionMs: null, salida: null };

function log(...args) {
    console.log(new Date().toISOString(), ...args);
}

function construir(vistaPrevia = false) {
    if (construyendo) {
        // Un build de publicación en curso no se pisa con otro. La vista previa
        // tampoco se encola aparte: el proceso es el mismo `astro build` sobre
        // el mismo proyecto, y dos a la vez se estorbarían en la caché.
        if (!vistaPrevia) {
            repetirAlTerminar = true;
            log('build en curso; se repetirá al terminar');
        } else {
            log('vista previa pedida con un build en curso; espera su turno');
        }
        return;
    }

    construyendo = true;
    const inicio = Date.now();
    log(vistaPrevia ? 'vista previa: empieza' : 'build: empieza');

    const argumentos = ['run', 'build', '--', '--outDir', vistaPrevia ? 'dist-vista-previa' : 'dist-nuevo'];

    const proceso = spawn('npm', argumentos, {
        cwd: '/app',
        // El token solo se pasa en la vista previa. En el build de publicación
        // el entorno va limpio, así que aunque el token esté configurado, el
        // sitio que se publica no puede contener un borrador.
        env: vistaPrevia
            ? { ...process.env, CERSEU_VISTA_PREVIA_TOKEN: TOKEN_VISTA_PREVIA }
            : { ...process.env, CERSEU_VISTA_PREVIA_TOKEN: '' },
        stdio: ['ignore', 'pipe', 'pipe'],
    });

    let cola = '';
    const recoger = (trozo) => {
        cola += trozo.toString();
        // Solo se guarda el final: un build entero son miles de líneas y lo
        // único que hace falta al fallar son las últimas.
        if (cola.length > 4000) cola = cola.slice(-4000);
    };
    proceso.stdout.on('data', recoger);
    proceso.stderr.on('data', recoger);

    proceso.on('close', async (codigo) => {
        let salida = codigo === 0 ? null : cola.trim();

        // `construyendo` sigue en true mientras se publica: otro build que
        // arrancara ahora escribiría en `dist-nuevo/` mientras se vacía.
        if (!vistaPrevia) {
            try {
                if (codigo === 0) await publicar();
            } catch (e) {
                codigo = 1;
                salida = `El build terminó pero no se pudo publicar: ${e.message}`;
            } finally {
                await rm(DIR_NUEVO, { recursive: true, force: true }).catch(() => {});
            }
        }

        const duracionMs = Date.now() - inicio;
        construyendo = false;
        const resultado = {
            estado: codigo === 0 ? 'correcto' : 'fallido',
            terminado: new Date().toISOString(),
            duracionMs,
            salida,
        };

        if (vistaPrevia) ultimaVistaPrevia = resultado;
        else ultimo = resultado;

        log(`${vistaPrevia ? 'vista previa' : 'build'}: ${resultado.estado} en ${duracionMs} ms`);

        if (repetirAlTerminar) {
            repetirAlTerminar = false;
            log('había peticiones durante el build: se reconstruye una vez más');
            construir();
        }
    });

    proceso.on('error', (e) => {
        construyendo = false;
        const resultado = {
            estado: 'fallido',
            terminado: new Date().toISOString(),
            duracionMs: Date.now() - inicio,
            salida: e.message,
        };
        if (vistaPrevia) ultimaVistaPrevia = resultado;
        else ultimo = resultado;
        log('build: no se pudo lanzar —', e.message);
    });
}

/** Rutas relativas de todos los ficheros bajo `dir`. */
async function ficheros(dir, base = dir) {
    const entradas = await readdir(dir, { withFileTypes: true }).catch(() => []);
    const listas = await Promise.all(
        entradas.map((e) => {
            const ruta = path.join(dir, e.name);
            return e.isDirectory() ? ficheros(ruta, base) : [path.relative(base, ruta)];
        })
    );
    return listas.flat();
}

/**
 * Pasa el build recién terminado de `dist-nuevo/` a `dist/` sin dejar huecos.
 *
 * Fichero a fichero con `rename`, que en el mismo disco es atómico: quien pida
 * una página en mitad del cambio recibe la vieja o la nueva, nunca un 404 ni
 * un fichero a medio escribir. No se cambia el directorio entero de una vez
 * porque Nginx tiene montado `dist/` en sí mismo, y un directorio renombrado
 * por encima no lo vería.
 *
 * El orden importa: primero lo que no es HTML —los assets con hash, que llevan
 * nombre nuevo y no pisan nada—, y después el HTML que los referencia. Así
 * ninguna página nueva apunta a un fichero que aún no está.
 */
async function publicar() {
    const nuevos = await ficheros(DIR_NUEVO);
    if (!nuevos.includes('index.html')) {
        throw new Error('el build no generó index.html; se mantiene el sitio anterior');
    }

    const esHtml = (f) => f.endsWith('.html');
    const mover = async (relativa) => {
        const destino = path.join(DIR_PUBLICADO, relativa);
        await mkdir(path.dirname(destino), { recursive: true });
        await rename(path.join(DIR_NUEVO, relativa), destino);
    };

    for (const f of nuevos.filter((f) => !esHtml(f))) await mover(f);
    for (const f of nuevos.filter(esHtml)) await mover(f);

    // Lo que el build nuevo ya no tiene: páginas de programas retirados,
    // assets de versiones anteriores. Los assets, solo pasados unos días.
    const vigentes = new Set(nuevos);
    const limite = Date.now() - DIAS_CONSERVANDO_ASSETS * 86_400_000;

    for (const f of await ficheros(DIR_PUBLICADO)) {
        if (vigentes.has(f)) continue;

        const ruta = path.join(DIR_PUBLICADO, f);
        if (f.startsWith('_astro' + path.sep) && (await stat(ruta)).mtimeMs > limite) continue;

        await rm(ruta, { force: true });
        await borrarDirectoriosVacios(path.dirname(ruta));
    }
}

async function borrarDirectoriosVacios(dir) {
    while (dir.startsWith(DIR_PUBLICADO + path.sep)) {
        // `rmdir` solo borra si está vacío: si falla, hay algo y se para.
        try {
            await rmdir(dir);
        } catch {
            return;
        }
        dir = path.dirname(dir);
    }
}

/**
 * Devuelve un fichero del directorio de vista previa.
 *
 * La ruta llega de fuera. Quien neutraliza un `../../etc/passwd` es el
 * `normalize` sobre una ruta con barra inicial: deja `/etc/passwd`, que al
 * resolverse cae dentro del directorio de vista previa y simplemente no existe.
 * La comprobación posterior es un cinturón sobre eso —hoy no llega a saltar—,
 * pero se queda: sin ella, cualquier cambio en cómo se arma la ruta volvería a
 * abrir el disco entero sin que nada avisara.
 */
async function ficheroDeVistaPrevia(rutaPedida) {
    const limpia = decodeURIComponent(rutaPedida.split('?')[0] ?? '');

    // Una ruta de página («/cursos/algo/») se sirve como su index.html.
    const relativa = limpia.endsWith('/') || !path.extname(limpia)
        ? path.join(limpia, 'index.html')
        : limpia;

    const destino = path.resolve(DIR_VISTA_PREVIA, '.' + path.posix.normalize('/' + relativa));

    if (destino !== DIR_VISTA_PREVIA && !destino.startsWith(DIR_VISTA_PREVIA + path.sep)) {
        return { codigo: 403, tipo: 'text/plain; charset=utf-8', cuerpo: Buffer.from('Fuera del directorio.') };
    }

    try {
        const cuerpo = await readFile(destino);
        return { codigo: 200, tipo: tipoDe(destino), cuerpo };
    } catch {
        return { codigo: 404, tipo: 'text/plain; charset=utf-8', cuerpo: Buffer.from('No existe en la vista previa.') };
    }
}

const TIPOS = {
    '.html': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.webp': 'image/webp',
    '.avif': 'image/avif',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.ico': 'image/x-icon',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
    '.xml': 'application/xml; charset=utf-8',
    '.txt': 'text/plain; charset=utf-8',
};

const tipoDe = (fichero) => TIPOS[path.extname(fichero).toLowerCase()] ?? 'application/octet-stream';

const servidor = createServer((peticion, respuesta) => {
    const responder = (codigo, cuerpo) => {
        respuesta.writeHead(codigo, { 'Content-Type': 'application/json; charset=utf-8' });
        respuesta.end(JSON.stringify(cuerpo));
    };

    const autorizado = () => TOKEN !== '' && peticion.headers.authorization === `Bearer ${TOKEN}`;

    if (peticion.url === '/estado' && peticion.method === 'GET') {
        return responder(200, { construyendo, ultimo });
    }

    // --- Vista previa de borradores -------------------------------------
    //
    // Todo lo de aquí exige el mismo token que la reconstrucción: sirve
    // contenido sin publicar, así que no puede quedar abierto ni siquiera
    // dentro de la red interna.

    if (peticion.url?.startsWith('/vista-previa')) {
        if (!autorizado()) return responder(401, { mensaje: 'Token inválido.' });

        if (!TOKEN_VISTA_PREVIA) {
            return responder(503, {
                mensaje: 'CERSEU_VISTA_PREVIA_TOKEN no está configurado: la vista previa está desactivada.',
            });
        }

        if (peticion.url === '/vista-previa' && peticion.method === 'POST') {
            construir(true);
            return responder(202, { mensaje: 'Vista previa encargada.', construyendo: true });
        }

        if (peticion.url === '/vista-previa/estado' && peticion.method === 'GET') {
            return responder(200, { construyendo, ultimo: ultimaVistaPrevia });
        }

        if (peticion.url.startsWith('/vista-previa/archivo') && peticion.method === 'GET') {
            const consulta = new URL(peticion.url, 'http://interno').searchParams;
            const ruta = consulta.get('ruta') ?? '/';

            return ficheroDeVistaPrevia(ruta).then(({ codigo, tipo, cuerpo }) => {
                respuesta.writeHead(codigo, {
                    'Content-Type': tipo,
                    // Contenido sin publicar: que no se quede en ninguna caché.
                    'Cache-Control': 'no-store, private',
                });
                respuesta.end(cuerpo);
            });
        }

        return responder(404, { mensaje: 'No existe.' });
    }

    if (peticion.url !== '/reconstruir' || peticion.method !== 'POST') {
        return responder(404, { mensaje: 'No existe.' });
    }

    if (!TOKEN) {
        return responder(500, {
            mensaje: 'CERSEU_BUILD_TOKEN no está configurado en el servicio de build.',
        });
    }

    if (peticion.headers.authorization !== `Bearer ${TOKEN}`) {
        return responder(401, { mensaje: 'Token inválido.' });
    }

    construir();

    // 202 y no 200: se acepta el encargo, no se ha terminado. Laravel no debe
    // esperar a que acabe un build de diez segundos dentro de un trabajo.
    return responder(202, { mensaje: 'Reconstrucción encargada.', construyendo: true });
});

servidor.listen(PUERTO, '0.0.0.0', () => {
    log(`servicio de build escuchando en el ${PUERTO}`);
    if (!TOKEN) log('AVISO: sin CERSEU_BUILD_TOKEN, las peticiones se rechazarán');
});
