<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Models\Programa;
use Illuminate\Http\JsonResponse;

/**
 * Plana docente, de solo lectura.
 *
 * Se declara campo a campo, como el resto de la API: la tabla `docentes`
 * guarda correo y enlaces personales, y no todo lo que el panel administra
 * tiene por qué acabar publicado.
 */
class DocenteApiController extends Controller
{
    public function index(): JsonResponse
    {
        // `activos()` y `ordenados()` son los scopes del modelo, los mismos que
        // usa Blade. `estado` es un booleano, no la cadena «activo»: filtrar a
        // mano aquí habría devuelto una lista vacía sin dar ningún error.
        $docentes = Docente::query()->activos()->ordenados()->get();

        return response()->json([
            'data' => $docentes->map(fn (Docente $d) => $this->comoArray($d))->values(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $docente = Docente::query()->activos()->with('programas')->where('slug', $slug)->first();

        if (! $docente) {
            return response()->json(['message' => 'Docente no encontrado.'], 404);
        }

        return response()->json([
            'data' => $this->comoArray($docente, completo: true),
        ]);
    }

    private function comoArray(Docente $docente, bool $completo = false): array
    {
        $base = [
            'slug' => $docente->slug,
            'nombre' => trim($docente->nombres . ' ' . $docente->apellidos),
            'nombre_completo' => $docente->nombre_completo,
            'grado' => $docente->grado ?: null,
            'foto' => $docente->foto_url,

            // Perfiles academicos publicos, tambien en el listado: la tarjeta de
            // la plana docente los enseña como iconos, y sin ellos aqui habria
            // que pedir las 20 fichas para pintar una rejilla. Son tres cadenas
            // cortas por docente.
            //
            // El correo NO se expone, ni aqui ni en la ficha: publicarlo en HTML
            // estatico es entregarselo a cualquier rastreador.
            'orcid' => $docente->orcid ?: null,
            'cti_vitae' => $docente->cti_vitae ?: null,
            'linkedin' => $docente->linkedin ?: null,
        ];

        if (! $completo) {
            return $base;
        }

        return $base + [
            'biografia' => $docente->biografia ?: null,
            'lineas_investigacion' => $docente->lineas_investigacion ?: null,
            // Hoy es lo unico que la ficha tiene que contar: ninguno de los 20
            // docentes registrados tiene biografia ni lineas de investigacion,
            // pero todos dictan algo. Sin esta lista la ficha repetiria el
            // nombre que ya se lee en el listado y nada mas.
            'programas' => $docente->programas
                ->filter(fn (Programa $p) => $p->estado === Programa::ESTADO_PUBLICADO)
                ->map(fn (Programa $p) => [
                    'nombre' => $p->nombre,
                    'slug' => $p->slug,
                    'tipo' => $p->tipoOferta()?->slug(),
                ])
                ->values(),
        ];
    }
}
