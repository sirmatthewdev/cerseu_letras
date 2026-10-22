<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Si lo guardado en el panel ya se ve en el sitio.
 *
 * Publicar tiene tres tramos y cualquiera puede fallar en silencio: la cola
 * tiene que recoger el trabajo, el servicio de build tiene que aceptarlo y el
 * build tiene que terminar bien. Hasta ahora, si algo de eso fallaba, el panel
 * decía «guardado» y el sitio seguía con la versión anterior sin que nadie lo
 * supiera. Aquí se juntan los tres tramos en una sola respuesta.
 */
class EstadoDePublicacion
{
    public const PUBLICADO = 'publicado';
    public const PENDIENTE = 'pendiente';
    public const PUBLICANDO = 'publicando';
    public const FALLIDO = 'fallido';
    public const ATASCADO = 'atascado';
    public const SIN_CONEXION = 'sin_conexion';
    public const DESCONOCIDO = 'desconocido';

    /** Desde cuándo hay cambios guardados que el sitio aún no muestra. */
    public const CLAVE_PENDIENTE = 'sitio.publicacion_pendiente_desde';

    /** El último encargo no llegó al servicio de build ni reintentando. */
    public const CLAVE_FALLO_AL_PEDIR = 'sitio.publicacion_fallo_al_pedir';

    /**
     * La espera normal son 60 s más, si el build aún no levanta, los
     * reintentos de 30 s y 120 s. Pasado esto ya no es espera: lo más probable
     * es que el servicio de cola esté parado.
     */
    private const MINUTOS_HASTA_ATASCADO = 10;

    public static function marcarPendiente(): void
    {
        // `add` y no `put`: interesa el primer cambio sin publicar, no el
        // último. Si se pisara, guardar sin parar ocultaría un atasco.
        Cache::add(self::CLAVE_PENDIENTE, now()->toIso8601String(), now()->addDays(2));
    }

    public static function encargoAceptado(): void
    {
        Cache::forget(self::CLAVE_PENDIENTE);
        Cache::forget(self::CLAVE_FALLO_AL_PEDIR);
    }

    public static function encargoFallido(string $motivo): void
    {
        Cache::forget(self::CLAVE_PENDIENTE);
        Cache::put(self::CLAVE_FALLO_AL_PEDIR, [
            'motivo' => $motivo,
            'en' => now()->toIso8601String(),
        ], now()->addDays(30));
    }

    /**
     * @return array{estado: string, desde: ?Carbon, detalle: ?string}|null
     *         null cuando no hay sitio que reconstruir (instalación con Blade).
     */
    public static function actual(): ?array
    {
        if (blank(config('sitio.reconstruccion.url'))) {
            return null;
        }

        // Se pinta en todas las páginas del panel: sin este margen, cada clic
        // sería una petición al servicio de build, y con el servicio caído,
        // dos segundos de espera.
        return Cache::remember('sitio.estado_de_publicacion', 10, fn () => self::calcular());
    }

    public static function olvidar(): void
    {
        Cache::forget('sitio.estado_de_publicacion');
    }

    /** Si el estado merece avisar en todas las páginas, no solo en el escritorio. */
    public static function hayProblema(?array $estado): bool
    {
        return in_array($estado['estado'] ?? null, [self::FALLIDO, self::ATASCADO, self::SIN_CONEXION], true);
    }

    /**
     * Cómo se cuenta cada estado en el panel. Aquí y no en la vista porque lo
     * pintan dos: el recuadro del escritorio y el aviso de todas las páginas.
     *
     * @return array{titulo: string, color: string, icono: string}
     */
    public static function presentar(array $estado): array
    {
        $hora = $estado['desde']?->timezone('America/Lima')->translatedFormat('j \d\e F, H:i');

        return match ($estado['estado']) {
            self::PUBLICADO => ['titulo' => 'El sitio está al día' . ($hora ? " · publicado el {$hora}" : ''), 'color' => 'success', 'icono' => 'heroicon-o-check-circle'],
            self::PENDIENTE => ['titulo' => 'Hay cambios guardados que se publicarán en un minuto', 'color' => 'info', 'icono' => 'heroicon-o-clock'],
            self::PUBLICANDO => ['titulo' => 'Publicando los cambios en el sitio…', 'color' => 'info', 'icono' => 'heroicon-o-arrow-path'],
            self::FALLIDO => ['titulo' => 'La última publicación falló: el sitio muestra la versión anterior', 'color' => 'danger', 'icono' => 'heroicon-o-x-circle'],
            self::ATASCADO => ['titulo' => 'Los cambios guardados no se están publicando', 'color' => 'danger', 'icono' => 'heroicon-o-exclamation-triangle'],
            self::SIN_CONEXION => ['titulo' => 'No se puede comprobar si el sitio está al día', 'color' => 'warning', 'icono' => 'heroicon-o-signal-slash'],
            default => ['titulo' => 'Sin publicaciones desde el último reinicio del servidor', 'color' => 'gray', 'icono' => 'heroicon-o-information-circle'],
        };
    }

    private static function calcular(): array
    {
        $pendiente = Cache::get(self::CLAVE_PENDIENTE);
        $pendienteDesde = $pendiente ? Carbon::parse($pendiente) : null;
        $falloAlPedir = Cache::get(self::CLAVE_FALLO_AL_PEDIR);

        try {
            $respuesta = Http::timeout(2)->get(self::urlDeEstado());
            $build = $respuesta->successful() ? $respuesta->json() : null;
        } catch (\Throwable) {
            $build = null;
        }

        if ($build === null) {
            return self::estado(self::SIN_CONEXION, null, 'El panel no consigue hablar con el servicio que publica el sitio.');
        }

        if ($build['construyendo'] ?? false) {
            return self::estado(self::PUBLICANDO);
        }

        if ($pendienteDesde) {
            return $pendienteDesde->diffInMinutes(now()) >= self::MINUTOS_HASTA_ATASCADO
                ? self::estado(self::ATASCADO, $pendienteDesde, 'Hay cambios guardados desde hace más de ' . self::MINUTOS_HASTA_ATASCADO . ' minutos que no se han publicado. Probablemente el servicio de cola esté detenido.')
                : self::estado(self::PENDIENTE, $pendienteDesde);
        }

        if ($falloAlPedir) {
            return self::estado(self::FALLIDO, Carbon::parse($falloAlPedir['en']), $falloAlPedir['motivo']);
        }

        $ultimo = $build['ultimo'] ?? [];
        $terminado = filled($ultimo['terminado'] ?? null) ? Carbon::parse($ultimo['terminado']) : null;

        return match ($ultimo['estado'] ?? null) {
            'correcto' => self::estado(self::PUBLICADO, $terminado),
            'fallido' => self::estado(self::FALLIDO, $terminado, $ultimo['salida'] ?? null),
            // El servicio guarda el último resultado en memoria: tras reiniciarse
            // no sabe nada hasta el próximo build, y eso no es un fallo.
            default => self::estado(self::DESCONOCIDO),
        };
    }

    private static function estado(string $estado, ?Carbon $desde = null, ?string $detalle = null): array
    {
        return ['estado' => $estado, 'desde' => $desde, 'detalle' => $detalle];
    }

    /** `/estado` vive junto a `/reconstruir` en el mismo servicio. */
    private static function urlDeEstado(): string
    {
        return preg_replace('#/reconstruir/?$#', '', rtrim((string) config('sitio.reconstruccion.url'), '/')) . '/estado';
    }
}
