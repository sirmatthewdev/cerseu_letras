<?php

namespace App\Filament\Widgets;

use App\Jobs\ReconstruirSitio;
use App\Support\EstadoDePublicacion;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/**
 * Si lo último que se guardó ya está en el sitio.
 *
 * Va arriba del todo en el escritorio: el sitio es estático, y «guardado» en
 * el panel no quiere decir «visible». Se refresca solo para que quien acaba de
 * guardar vea pasar de «pendiente» a «publicado» sin recargar.
 */
class PublicacionDelSitio extends Widget
{
    protected static ?int $sort = -20;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.publicacion-del-sitio';

    public static function canView(): bool
    {
        return EstadoDePublicacion::actual() !== null;
    }

    public function volverAPublicar(): void
    {
        EstadoDePublicacion::marcarPendiente();
        EstadoDePublicacion::olvidar();

        // Sin retraso: aquí nadie está guardando en ráfaga, alguien ha pulsado
        // un botón y espera verlo arrancar.
        ReconstruirSitio::dispatch();

        Notification::make()
            ->title('Publicación encargada')
            ->body('El sitio se está reconstruyendo; en un par de minutos se verán los cambios.')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        return ['publicacion' => EstadoDePublicacion::actual()];
    }
}
