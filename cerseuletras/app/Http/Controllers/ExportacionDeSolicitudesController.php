<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\TipoOferta;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación de solicitudes a CSV.
 *
 * Vivía en el panel Blade, que ya no existe. No se rehízo como acción de
 * Filament porque el formato costó afinarlo y funciona: separador de punto y
 * coma y BOM UTF-8, que es lo que hace que Excel en castellano abra el fichero
 * sin partir las columnas ni romper las tildes. Filament solo pone el botón y
 * apunta aquí.
 *
 * Se sirve en streaming y por lotes: la tabla de solicitudes crece sola y sin
 * techo, y cargarla entera en memoria para exportarla es un problema que llega
 * sin avisar el día que alguien pulsa el botón tras una campaña.
 */
class ExportacionDeSolicitudesController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $tipo = TipoOferta::desdeSlug($request->string('tipo')->toString());

        $consulta = Lead::query()
            ->with('programa:id,nombre,mencion')
            ->when($request->filled('programa'), fn ($q) => $q->where('programa_id', $request->integer('programa')))
            ->when($tipo, fn ($q) => $q->deTipo($tipo))
            ->latest();

        $nombre = 'solicitudes-' . ($tipo?->slug() ?? 'todas') . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($consulta) {
            $salida = fopen('php://output', 'w');

            // BOM UTF-8: sin él, Excel en Windows rompe las tildes.
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, ['Fecha', 'Tipo', 'Nombres', 'Apellidos', 'Correo', 'Teléfono', 'País', 'Región', 'Oferta'], ';');

            $consulta->chunk(500, function ($filas) use ($salida) {
                foreach ($filas as $lead) {
                    fputcsv($salida, [
                        $lead->created_at?->format('d/m/Y H:i'),
                        $lead->tipo?->singular() ?? '—',
                        $lead->nombres,
                        $lead->apellidos,
                        $lead->correo,
                        $lead->telefono,
                        $lead->pais,
                        $lead->region,
                        $lead->programa?->titulo_completo ?? '—',
                    ], ';');
                }
            });

            fclose($salida);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
