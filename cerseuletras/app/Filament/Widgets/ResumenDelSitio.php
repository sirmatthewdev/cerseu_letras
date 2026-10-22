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
 * cuánto se ve en el sitio, cuánto está oculto, quién enseña y cuántas
 * solicitudes han llegado. El escritorio vacío que trae Filament de fábrica no
 * dice nada.
 *
 * «No visibles» va en gris y no en color de aviso: con cursos que van por
 * temporadas, tener fichas ocultas es lo normal, no algo pendiente.
 */
class ResumenDelSitio extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $visibles = Programa::where('estado', Programa::ESTADO_PUBLICADO)->count();
        $noVisibles = Programa::where('estado', '!=', Programa::ESTADO_PUBLICADO)->count();

        return [
            Stat::make('Programas visibles', $visibles)
                ->description('En el sitio ahora mismo')
                ->color('success'),

            Stat::make('No visibles', $noVisibles)
                ->description('Ocultos del sitio; se pueden revisar con «Vista previa»')
                ->color('gray'),

            Stat::make('Docentes', Docente::count())
                ->description(Evento::count() . ' eventos cargados')
                ->color('gray'),

            Stat::make('Solicitudes', Lead::count())
                ->description('Recibidas por el formulario')
                ->color('gray'),
        ];
    }
}
