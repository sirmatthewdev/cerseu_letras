<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $table = 'site_settings';

    protected $fillable = [
        'site_name',
        'site_description',
        'logo_path',
        'favicon_path',
        'primary_color',
        'secondary_color',
        'header_text',
        'footer_text',
        'talleres_hero_titulo',
        'talleres_hero_texto',
        'talleres_hero_claim',
        'cursos_hero_titulo',
        'cursos_hero_texto',
        'cursos_hero_claim',
        'talleres_hero_imagen',
        'cursos_hero_imagen',

        // Especialización se añadió como tipo después (2026_08_24_140000) y sus
        // cuatro columnas se crearon, pero nadie las trajo aquí. Sin estar en
        // `$fillable`, `fill()` las descarta sin decir nada: el hero de
        // /especializaciones no se podía editar desde ningún panel y ninguna
        // pantalla se quejaba, porque guardar «funcionaba» — solo que sin
        // guardar esos cuatro campos.
        'especializaciones_hero_titulo',
        'especializaciones_hero_texto',
        'especializaciones_hero_claim',
        'especializaciones_hero_imagen',
        'home_hero_kicker',
        'home_hero_titulo',
        'home_hero_claim',
        'home_hero_texto',
        'home_hero_cta1_texto',
        'home_hero_cta1_url',
        'home_hero_cta2_texto',
        'home_hero_cta2_url',
        'home_stat_docentes',
        'popup_retardo_ms',
        'popup_frecuencia',
        'popup_auto_avance',
        'email',
        'email_admision',
        'email_tramites',
        'telefono',
        'anexo',
        'direccion',
        'horario_atencion',
        'facebook',
        'instagram',
        'twitter',
        'linkedin',
        'youtube',
        'tiktok',
        'web_facultad',
        'directorio_facultad',
    ];

    /**
     * Get the site settings (singleton pattern with cache)
     *
     * La configuración se consulta desde la barra superior, el menú, el pie y
     * varias secciones. `once()` la resuelve una sola vez por petición: sin
     * ello cada llamada golpea el almacén de caché, que con el driver de base
     * de datos es una consulta más por cada una.
     */
    public static function get(): ?self
    {
        return once(function () {
            return Cache::remember('site_settings', 3600, function () {
                return self::first();
            });
        });
    }

    /**
     * Clear the settings cache
     */
    public static function clearCache(): void
    {
        Cache::forget('site_settings');
        \Illuminate\Support\Once::flush();
    }

    /**
     * Get a specific setting value
     */
    public static function getValue(string $key, $default = null)
    {
        $settings = self::get();
        return $settings?->{$key} ?? $default;
    }

    /**
     * Punto único para los datos de contacto del sitio.
     *
     * Antes convivían dos fuentes: el panel escribía en esta tabla mientras que
     * /tramites, /admision y las fichas de programa leían `config/contacts.php`.
     * Resultado: cambiar el teléfono desde el panel solo surtía efecto en parte
     * del sitio. Ahora manda siempre la base de datos y el fichero de config
     * queda como respaldo para una instalación recién creada.
     *
     * Claves: general | admision | tramites | telefono | whatsapp
     */
    public static function contacto(string $clave): ?string
    {
        $s = self::get();

        return match ($clave) {
            'general' => $s?->email ?: config('contacts.general'),
            // El correo de admisión cae al general antes que al fichero.
            'admision' => $s?->email_admision ?: ($s?->email ?: config('contacts.admision')),
            'tramites' => $s?->email_tramites ?: config('contacts.tramites'),
            'telefono' => $s?->telefono ?: config('contacts.telefono'),
            'whatsapp' => self::whatsappUrl(),
            default => config('contacts.' . $clave),
        };
    }

    /**
     * El icono de la pestaña para las páginas que sirve Laravel: el panel,
     * /login y /profile. La misma regla que el sitio de Astro —manda el que
     * suba la Unidad desde Configuración y, mientras no suba ninguno, el de la
     * marca, que nginx sirve desde el `dist/`—, para que las dos mitades del
     * dominio no enseñen iconos distintos.
     */
    public static function faviconUrl(): string
    {
        $ruta = self::get()?->favicon_path;

        return filled($ruta) ? asset('storage/' . $ruta) : asset('favicon-32.png');
    }

    /**
     * Enlace de WhatsApp derivado del teléfono para no mantener dos campos que
     * puedan contradecirse. Asume prefijo peruano cuando el número es local.
     */
    public static function whatsappUrl(): ?string
    {
        $telefono = self::get()?->telefono;
        $digitos = preg_replace('/\D/', '', (string) $telefono);

        if ($digitos === '') {
            return config('contacts.whatsapp');
        }

        // Números locales (9 dígitos) → se antepone el código de país.
        if (strlen($digitos) === 9) {
            $digitos = '51' . $digitos;
        }

        return 'https://wa.me/' . $digitos;
    }

    /**
     * Ajustes del popup de anuncios de la portada, con sus valores por defecto.
     *
     * `frecuencia` decide cada cuánto se vuelve a ver:
     *   sesion → una vez por visita (por defecto)
     *   dia    → una vez al día, aunque se cierre el navegador
     *   siempre→ en cada carga (útil solo para algo muy urgente)
     */
    public static function ajustesPopup(): array
    {
        $s = self::get();

        return [
            'retardo' => (int) ($s?->popup_retardo_ms ?: 1200),
            'frecuencia' => $s?->popup_frecuencia ?: 'sesion',
            'autoAvance' => (bool) ($s?->popup_auto_avance ?? false),
        ];
    }

    /**
     * La tabla es un singleton: `get()` siempre devuelve `first()`, así que una
     * segunda fila no se mostraría en ninguna parte pero sí desconcertaría a
     * quien la creara desde el panel (guarda, no ve el cambio, vuelve a
     * guardar). Se corta al crear, que es donde puede aparecer.
     */
    protected static function booted(): void
    {
        static::creating(function (self $ajustes) {
            if (self::query()->exists()) {
                throw new \RuntimeException(
                    'site_settings es una tabla de una sola fila: edita la existente en lugar de crear otra.'
                );
            }
        });

        // Cualquier escritura invalida la caché compartida por barra superior,
        // menú, pie y secciones de contacto.
        static::saved(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }
}
