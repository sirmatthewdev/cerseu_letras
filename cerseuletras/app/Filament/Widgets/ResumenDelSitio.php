<?php

namespace App\Filament\Widgets;

use App\Models\Docente;
use App\Models\Evento;
use App\Models\Lead;
use App\Models\Programa;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo primero que se ve al entrar al panel.
 *
 * Cuatro cifras, elegidas por lo que responden y no por lo que se puede contar:
 * cuánto hay publicado, cuánto está a medias, quién enseña y cuántas solicitudes
 * han llegado. El escritorio vacío que trae Filament de fábrica no dice nada.
 *
 * Los borradores llevan color de aviso cuando los hay: son trabajo empezado que
 * nadie ve, y es justo lo que conviene recordar al entrar.
 */
class ResumenDelSitio extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $publicados = Programa::where('estado', Programa::ESTADO_PUBLICADO)->count();
        $borradores = Programa::where('estado', Programa::ESTADO_BORRADOR)->count();
        $proximamente = Programa::where('estado', Programa::ESTADO_PROXIMAMENTE)->count();

        return [
            Stat::make('Programas publicados', $publicados)
                ->description($proximamente > 0 ? "{$proximamente} anunciados como próximamente" : 'En el sitio ahora mismo')
                ->color('success'),

            Stat::make('Borradores', $borradores)
                ->description($borradores > 0 ? 'Sin publicar; se pueden ver con «Vista previa»' : 'Nada a medias')
                ->color($borradores > 0 ? 'warning' : 'gray'),

            Stat::make('Docentes', Docente::count())
                ->description(Evento::count() . ' eventos cargados')
                ->color('gray'),

            Stat::make('Solicitudes', Lead::count())
                ->description('Recibidas por el formulario')
                ->color('gray'),
        ];
    }
}
