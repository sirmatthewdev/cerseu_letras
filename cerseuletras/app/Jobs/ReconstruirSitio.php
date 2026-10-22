<?php

namespace App\Jobs;

use App\Support\EstadoDePublicacion;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Encarga al sitio en Astro que se reconstruya.
 *
 * Al separar el frontend, publicar dejó de ser instantáneo: el panel guarda en
 * la base y el sitio estático sigue sirviendo lo anterior. Esto cierra el
 * ciclo — es la pieza que la propuesta llamaba «reconstrucción disparada por
 * publicaciones».
 *
 * `ShouldBeUnique` con retraso es lo que evita el problema evidente: quien
 * edita guarda seis veces seguidas, y sin agrupar serían seis builds. Mientras
 * hay uno esperando, los demás avisos no encolan nada.
 */
class ReconstruirSitio implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /** Un único trabajo pendiente a la vez, se identifique quien lo pida. */
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'reconstruir-sitio';
    }

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function handle(): void
    {
        $url = config('sitio.reconstruccion.url');
        $token = config('sitio.reconstruccion.token');

        // Sin URL configurada no es un fallo: es una instalación que todavía
        // sirve el sitio con Blade y no tiene nada que reconstruir.
        if (blank($url)) {
            return;
        }

        if (blank($token)) {
            Log::warning('Reconstrucción del sitio: falta CERSEU_BUILD_TOKEN, no se pide.');
            EstadoDePublicacion::encargoFallido('Falta CERSEU_BUILD_TOKEN en el .env: el sitio no se puede reconstruir.');

            return;
        }

        $respuesta = Http::withToken($token)->timeout(15)->post($url);

        Cache::put('sitio.ultima_reconstruccion', [
            'pedida_en' => now()->toDateTimeString(),
            'aceptada' => $respuesta->successful(),
            'codigo' => $respuesta->status(),
        ], now()->addDays(30));

        if (! $respuesta->successful()) {
            // Se lanza para que la cola reintente: un build service que aún no
            // ha levantado es exactamente el caso que el reintento resuelve.
            throw new \RuntimeException(
                'El servicio de build respondió ' . $respuesta->status() . ': ' . $respuesta->body()
            );
        }

        EstadoDePublicacion::encargoAceptado();
    }

    /** Agotados los reintentos: que el panel lo diga en vez de callarlo. */
    public function failed(?\Throwable $error): void
    {
        EstadoDePublicacion::encargoFallido($error?->getMessage() ?: 'El servicio de build no aceptó el encargo.');
    }
}
