<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CronogramaAdmision;
use App\Models\DirectorioCerseu;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

/**
 * Identidad y navegación del sitio.
 *
 * Es la pieza que hace practicable la regla de la propuesta —«lo que cambia
 * entre unidades sale de configuración, nunca del código»— también en el
 * frontend. Sin esto, el sitio en Astro tendría el nombre, el correo, el
 * teléfono y el menú escritos en una plantilla, que es el mismo error que se
 * corrigió en los seeders y en la migración, solo que en otro lenguaje.
 *
 * Cambiar el CERSEU por otra unidad debería ser cambiar filas de
 * `site_settings` y `menu_items`, no editar un `.astro`.
 */
class SitioApiController extends Controller
{
    /**
     * Identidad, contacto y redes.
     *
     * El contacto sale de `SiteSetting::contacto()`, el mismo accesor que usa
     * Blade, para que ambos sitios no puedan contradecirse: ahí está resuelta
     * la cascada campo del panel → valor de respaldo, y la derivación del
     * enlace de WhatsApp desde el teléfono.
     */
    public function configuracion(): JsonResponse
    {
        $ajustes = SiteSetting::get();

        return response()->json([
            'data' => [
                'nombre' => $ajustes?->site_name,
                'descripcion' => $ajustes?->site_description,
                'logo' => $this->imagen($ajustes?->logo_path, 'images/logo-cerseu.webp'),
                'favicon' => $this->imagen($ajustes?->favicon_path),
                'contacto' => [
                    'email' => SiteSetting::contacto('general'),
                    'email_admision' => SiteSetting::contacto('admision'),
                    'email_tramites' => SiteSetting::contacto('tramites'),
                    'telefono' => SiteSetting::contacto('telefono'),
                    'anexo' => $ajustes?->anexo,
                    'whatsapp' => SiteSetting::contacto('whatsapp'),
                    'direccion' => $ajustes?->direccion,
                    'horario' => $ajustes?->horario_atencion,
                ],
                // Accesos a la Facultad, que en Blade ocupan la barra superior.
                // Se editan en Configuracion: un cambio de dominio de Letras no
                // deberia obligar a tocar el sitio.
                'facultad' => [
                    'web' => $ajustes?->web_facultad,
                    'directorio' => $ajustes?->directorio_facultad,
                ],
                // Hero de la portada. Los textos se editan en Configuracion;
                // tenerlos en la plantilla del sitio obligaria a desplegar
                // para cambiar un titular.
                'portada' => [
                    'kicker' => $ajustes?->home_hero_kicker,
                    'titulo' => $ajustes?->home_hero_titulo,
                    // El mismo papel que el `claim` de los heros de oferta: la
                    // frase corta que va entre el nombre y el parrafo.
                    'claim' => $ajustes?->home_hero_claim,
                    'texto' => $ajustes?->home_hero_texto,
                    'acciones' => array_values(array_filter([
                        $ajustes?->home_hero_cta1_texto ? [
                            'texto' => $ajustes->home_hero_cta1_texto,
                            'url' => $ajustes->home_hero_cta1_url ?: '/',
                        ] : null,
                        $ajustes?->home_hero_cta2_texto ? [
                            'texto' => $ajustes->home_hero_cta2_texto,
                            'url' => $ajustes->home_hero_cta2_url ?: '/',
                        ] : null,
                    ])),
                    // Las mismas fotos del campus que rota el carrusel en
                    // Blade. Van absolutas y en webp, resueltas contra la URL
                    // publica por el middleware ForzarUrlPublica.
                    // Cuantos docentes se anuncian en la banda de cifras. Es
                    // un campo del panel y no el conteo real: la Unidad cuenta
                    // los docentes RENACYT, que no son todos los registrados.
                    // Sin valor, el sitio cae al numero de fichas publicadas.
                    'docentes' => $ajustes?->home_stat_docentes ?: null,
                    'imagenes' => array_map(
                        fn (string $f) => asset("images/{$f}"),
                        ['campus-aerea.webp', 'campus-aerea-2.webp', 'campus-fachada.webp']
                    ),
                ],

                // Bloque «Como inscribirte» de la portada, con sus pasos.
                'inscripcion' => $this->inscripcion(),

                'redes' => array_filter([
                    'facebook' => $ajustes?->facebook,
                    'instagram' => $ajustes?->instagram,
                    'tiktok' => $ajustes?->tiktok,
                    'youtube' => $ajustes?->youtube,
                    'linkedin' => $ajustes?->linkedin,
                ]),
            ],
        ]);
    }

    /**
     * El menú, ya resuelto y anidado.
     *
     * Las URLs salen del accesor `enlace`, que prefiere `route_name` sobre una
     * URL escrita a mano —sobrevive a un cambio de ruta— y descarta las rutas
     * que ya no existen. Resolverlo aquí y no en Astro evita que el sitio
     * tenga que conocer el mapa de rutas de Laravel.
     */
    public function menu(): JsonResponse
    {
        $raiz = MenuItem::query()
            ->visibles()
            ->whereNull('parent_id')
            ->with(['hijos' => fn ($q) => $q->visibles()])
            ->orderBy('orden')
            ->get();

        return response()->json([
            'data' => $raiz
                ->map(fn (MenuItem $item) => $this->comoArray($item))
                // Se omite lo que no lleva a ninguna parte: una entrada sin
                // destino y sin hijos apuntaria a un enlace vacio en la barra.
                // Pasa cuando el destino guardado ya no existe —una seccion
                // retirada—, y es preferible que falte una entrada a que el
                // menu ofrezca un enlace muerto.
                ->reject(fn (array $item) => $item['enlace'] === null && $item['hijos'] === [])
                ->values(),
        ]);
    }

    /**
     * Los pasos que la Unidad edita en el panel, en «Cronograma de admisión».
     *
     * Devuelve null si no hay ninguno visible, para que el sitio pueda omitir
     * la seccion entera en vez de pintar un titulo sobre un hueco.
     */
    private function inscripcion(): ?array
    {
        $bloque = CronogramaAdmision::get();

        if (! $bloque || ! $bloque->is_visible) {
            return null;
        }

        $pasos = $bloque->pasos->where('is_visible', true)->sortBy('orden')->values();

        if ($pasos->isEmpty()) {
            return null;
        }

        return [
            'eyebrow' => $bloque->eyebrow,
            'titulo' => $bloque->titulo,
            'boton' => $bloque->boton_texto ? [
                'texto' => $bloque->boton_texto,
                'url' => $bloque->boton_url ?: '/',
            ] : null,
            'pasos' => $pasos->map(fn ($paso) => [
                'titulo' => $paso->titulo,
                'detalle' => $paso->detalle ?: null,
                'fecha' => $paso->fecha_display ?: null,
                'publico' => $paso->publico ?: null,
                'destacado' => (bool) $paso->destacado,
                // El SVG ya resuelto: el catalogo de iconos vive en el modelo,
                // y duplicarlo en el frontend seria mantenerlo dos veces.
                'icono_path' => $paso->icono_path,
            ])->all(),
        ];
    }

    private function comoArray(MenuItem $item): array
    {
        $hijos = $item->hijos
            ->map(fn (MenuItem $hijo) => $this->comoArray($hijo))
            ->reject(fn (array $hijo) => $hijo['enlace'] === null && $hijo['hijos'] === [])
            ->values();

        return [
            'etiqueta' => $item->etiqueta,
            'enlace' => $this->enlace($item),
            'nueva_pestana' => (bool) $item->nueva_pestana,
            'hijos' => $hijos->all(),
        ];
    }

    /**
     * Directorio del CERSEU, agrupado por unidad.
     *
     * Lo administra el panel y hoy esta vacio. El endpoint existe igual: sin
     * el, el dia que la Unidad cargue su equipo no apareceria en ninguna parte
     * y habria que volver a tocar codigo. El orden —autoridades primero,
     * despues personal administrativo— lo decide el modelo, el mismo que usaba
     * la vista anterior.
     */
    public function directorio(): JsonResponse
    {
        $grupos = DirectorioCerseu::agrupadosPorUnidad();

        return response()->json([
            'data' => $grupos
                ->map(fn ($personas, $unidad) => [
                    'unidad' => $unidad,
                    'personas' => $personas->map(fn (DirectorioCerseu $p) => [
                        'nombre' => $p->nombre_persona,
                        'cargo' => $p->cargo,
                        'anexo' => $p->anexo ?: null,
                        // El correo si se publica: es el dato por el que se
                        // consulta un directorio, y ya esta en el pie.
                        'correo' => $p->correo_persona ?: null,
                    ])->values(),
                ])
                ->values(),
        ]);
    }

    /**
     * URL de una imagen del panel, con el respaldo que ya usaba Blade.
     *
     * `logo_path` es una ruta DENTRO del disco publico —«settings/xxx.webp»—,
     * no una direccion: entregarla tal cual dejaba a Astro con una cadena que
     * no apunta a ninguna parte, y por eso la cabecera acababa escribiendo el
     * nombre del CERSEU en texto en vez de pintar su logotipo.
     *
     * El respaldo es el mismo de la vista de Blade: sin logo subido se sirve el
     * que viene con el proyecto, no un hueco.
     */
    private function imagen(?string $ruta, ?string $respaldo = null): ?string
    {
        if (filled($ruta)) {
            return asset('storage/' . $ruta);
        }

        return $respaldo ? asset($respaldo) : null;
    }

    /**
     * Ruta relativa si es interna; URL completa si apunta fuera.
     *
     * `route()` devuelve la URL absoluta de ESTA aplicacion, y eso saca al
     * visitante del sitio que esta viendo: en el frontend desacoplado, pulsar
     * «Cursos» en la cabecera te llevaba al sitio en Blade. Una ruta interna
     * es una ruta, no una direccion: cada frontend la resuelve contra su
     * propio origen.
     */
    private function enlace(MenuItem $item): ?string
    {
        $enlace = $item->enlace;

        if (! $enlace) {
            return null;
        }

        // Solo las que genero `route()`; una URL escrita a mano en el panel
        // apunta a donde quiso quien la escribio y se respeta tal cual.
        if (! $item->route_name) {
            return $enlace;
        }

        $ruta = parse_url($enlace, PHP_URL_PATH) ?: '/';
        $consulta = parse_url($enlace, PHP_URL_QUERY);

        return $consulta ? $ruta . '?' . $consulta : $ruta;
    }
}
