<?php

namespace App\Providers\Filament;

use App\Models\SiteSetting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel de administración en Filament.
 *
 * Sustituye, recurso a recurso, a los 18 controladores y 40 vistas escritos a
 * mano en `Http/Controllers/Admin`. Va por partes y no de golpe porque el panel
 * es la herramienta de trabajo de la Unidad: dejarlo a medias un día entero no
 * es una opción.
 *
 * Tres decisiones de montaje:
 *
 * 1. **Vive en `/panel`, no en `/admin`.** El panel de siempre sigue en
 *    `/admin` y sigue funcionando. Mientras dure la migración conviven los dos:
 *    cada recurso que se pasa se retira de allí, y cuando no quede ninguno se
 *    mueve Filament a `/admin` y se borra el resto. Instalarlo directamente en
 *    `/admin` habría dejado el panel roto desde el primer minuto.
 *
 * 2. **No trae su propio login.** La aplicación ya tiene uno en `/login` con su
 *    sesión, su recuerdo de contraseña y su verificación de correo. Un segundo
 *    formulario significaría dos sitios donde iniciar sesión y dos donde
 *    arreglar cualquier cosa que falle.
 *
 * 3. **El acceso lo decide `User::canAccessPanel()`**, que comprueba el mismo
 *    `role === 'admin'` que el middleware `isAdmin` de las rutas de siempre. Sin
 *    ese método, Filament deja entrar a cualquiera en local y a nadie fuera de
 *    local: las dos respuestas equivocadas.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('panel')
            ->colors([
                // Los de la marca, no el ámbar de fábrica. `Color::hex` genera
                // la escala entera a partir del color institucional.
                'primary' => Color::hex('#143B63'),
                'warning' => Color::hex('#B6A350'),
            ])
            ->brandName('CERSEU Letras')
            // Sin esto el navegador pedia /favicon.ico, que esta vacio, y la
            // pestaña del panel salia sin icono. Closure para que se lea al
            // pintar, no al registrar el panel (ver SiteSetting::faviconUrl).
            ->favicon(fn (): string => SiteSetting::faviconUrl())
            ->font('Inter')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\ResumenDelSitio::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
