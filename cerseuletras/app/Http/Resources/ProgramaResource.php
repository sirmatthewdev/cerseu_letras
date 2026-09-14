<?php

namespace App\Http\Resources;

use App\Models\Programa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrato público de un programa hacia el sitio en Astro.
 *
 * Se declara campo a campo a propósito, sin `$this->resource->toArray()`: la
 * tabla `programas` arrastra columnas de la etapa de Posgrado —`creditos`,
 * `costo_por_credito`, `semestres_inversion`— que no pintan nada en la oferta
 * del CERSEU. Volcar el modelo entero las publicaría en la API y ataría el
 * frontend a un esquema que queremos poder limpiar.
 *
 * El resto del contenido largo —objetivos, plan de estudios, perfiles, los
 * documentos— sí va, aunque hoy los 39 programas lo tengan vacío: son campos
 * que el panel edita, y no exponerlos significaría que el día que la Unidad
 * escriba los objetivos de un curso no aparecerían en ninguna parte. La ficha
 * oculta cada bloque que llegue vacío, así que no cuesta nada mientras no se
 * usen.
 *
 * `medidas` sale ya formateada («12 horas académicas») porque la regla de qué
 * unidad corresponde a cada tipo vive en TipoOferta, no en la plantilla: si
 * Astro las recompusiera, esa regla acabaría duplicada en dos lenguajes.
 */
class ProgramaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tipo = $this->resource->tipoOferta();

        return [
            'slug' => $this->resource->slug,
            'nombre' => $this->resource->nombre,
            'tipo' => $tipo?->slug(),
            'tipo_label' => $tipo?->singular(),
            'mencion' => $this->resource->mencion ?: null,
            'modalidad' => $this->resource->modalidad ?: null,
            'sumilla' => $this->resource->sumilla ?: null,
            'medidas' => $this->resource->medidasFormateadas(),
            // Inversión económica, con el orden y los rótulos que fijó la
            // Unidad (Obs. N.º 2): costo total, modalidades de pago, diploma,
            // matrícula y condiciones. Sale ya resuelta por los accesores del
            // modelo, que son el punto único donde conviven el formato que
            // carga el panel y las cuotas planas de la versión anterior:
            // recomponerlo en el sitio sería mantener esa regla dos veces, en
            // dos lenguajes.
            'inversion' => $this->inversion(),
            'estado' => $this->resource->estado,

            // Contenido largo de la ficha. Se envía siempre —también en el
            // listado— porque son unos pocos kB y el sitio se genera una vez,
            // no una petición por visita.
            'objetivos' => $this->resource->objetivos_academicos ?: null,
            'plan_estudios' => $this->resource->plan_estudios ?: null,
            'perfil_ingresante' => $this->resource->perfil_ingresante ?: null,
            'perfil_graduado' => $this->resource->perfil_graduado ?: null,
            'por_que' => $this->resource->por_que_text ?: null,
            'vacantes' => $this->resource->vacantes ?: null,
            'duracion' => $this->resource->duracion ?: null,
            // Rotulo y contenido por separado (Obs. N.o 4): «Grado que otorga»
            // es incorrecto en un taller, que no otorga ningun grado, y ambos
            // se editan. Null cuando no hay nada que anunciar, para que la
            // ficha omita el dato en vez de dejar una etiqueta suelta.
            'grado_otorga' => $this->resource->denominacion_otorga['texto'] ?? null,
            'grado_otorga_label' => $this->resource->denominacion_otorga['label'] ?? null,
            'fecha_limite' => $this->resource->fecha_limite_inscripcion
                ? $this->resource->fecha_limite_inscripcion->toDateString()
                : null,

            // Documentos descargables, tal como los sube el panel.
            'documentos' => array_values(array_filter([
                $this->documento('Plan de estudios', $this->resource->plan_url),
                $this->documento('Horario', $this->resource->horario_url),
                // Del accesor: reancla al host actual un enlace que el panel
                // guardo como absoluto en otro entorno, y respeta los externos.
                $this->resource->brochure_link ? ['titulo' => 'Brochure', 'url' => $this->resource->brochure_link] : null,
                $this->documento('Admisión', $this->resource->admision_pdf_url),
            ])),
            // URL absoluta y ya resuelta: el accesor aplica el mismo respaldo
            // que el sitio en Blade (una foto del campus segun el tipo) cuando
            // el programa no tiene imagen propia. Enviar `imagen` en crudo
            // obligaria a reimplementar ese respaldo en cada consumidor.
            'imagen' => $this->resource->imagen_url,

            /*
             * Si la de arriba es suya o es el respaldo.
             *
             * El respaldo sirve para la ficha —una foto del campus presidiendo
             * una pagina es una foto del campus— y no sirve para el listado:
             * ahi son 39 tarjetas seguidas con la misma fotografia, que se lee
             * como un fallo de pintado y no como una decision. Con este dato el
             * consumidor elige, que es algo que solo el puede saber; sin el
             * tendria que adivinarlo comparando la URL con el nombre del
             * archivo de respaldo.
             */
            'imagen_propia' => filled($this->resource->imagen),

            // Sin URL: la API entrega identidad (`tipo` + `slug`), no rutas.
            // Devolver la URL del sitio en Blade ataba al consumidor a la
            // estructura de enlaces de OTRO sitio, y el de Astro acababa
            // expulsando al visitante en cada «Mas informacion». Cada
            // frontend compone sus propias rutas.

            // Solo cuando se han cargado: el listado no paga la consulta.
            'docentes' => $this->whenLoaded('docentes', fn () => $this->resource->docentes
                ->sortBy('pivot.orden')
                ->map(fn ($docente) => [
                    'nombre' => trim($docente->nombres . ' ' . $docente->apellidos),
                    'slug' => $docente->slug,
                    'grado' => $docente->grado ?: null,
                    'rol' => $docente->pivot->rol ?: null,
                    'es_coordinador' => (bool) $docente->pivot->es_coordinador,
                    // «Coordinador» o «Coordinadora», según se configure en
                    // cada programa (Obs. N.º 1). Antes la ficha lo rotulaba
                    // siempre en masculino y no había forma de cambiarlo.
                    'denominacion' => $docente->pivot->es_coordinador
                        ? Programa::denominacionCoordinador($docente->pivot->coordinador_denominacion ?? null)
                        : null,
                ])->values()->all()),
        ];
    }

    /**
     * Inversión económica, o null si el programa no tiene ninguna cargada.
     *
     * Devolver null y no una estructura vacía permite que la ficha omita el
     * bloque entero en lugar de pintar un encabezado sobre un hueco.
     *
     * @return array<string, mixed>|null
     */
    private function inversion(): ?array
    {
        $inversion = (array) ($this->resource->inversion_economica ?? []);

        // `data_get` para poder alcanzar tambien los anidados, como
        // derecho_inscripcion.bachiller_unmsm.
        $importe = function (string $clave) use ($inversion) {
            $valor = data_get($inversion, $clave);

            return is_numeric($valor) ? (float) $valor : null;
        };

        $bloque = [
            // Del accesor y no del array en crudo: prioriza el importe cerrado
            // que carga el panel y, si no lo hay, lo calcula desde las tarifas.
            // Leerlo de `inversion_economica` dejaba sin total a los programas
            // que lo tienen calculado.
            'costo_total' => $this->resource->costo_total !== null
                ? (float) $this->resource->costo_total
                : null,
            'costo_diploma' => $importe('costo_diploma'),
            'costo_matricula' => $importe('costo_matricula'),
            'derecho_inscripcion' => array_filter([
                'bachiller_unmsm' => $importe('derecho_inscripcion.bachiller_unmsm'),
                'otras_universidades' => $importe('derecho_inscripcion.otras_universidades'),
            ], fn ($v) => $v !== null) ?: null,
            'modalidades' => $this->resource->modalidades_de_pago,
            'condiciones' => $this->resource->condiciones_de_pago,
        ];

        // Se decide sobre los valores ya resueltos y no sobre el array en
        // crudo: hay programas sin `inversion_economica` cuyo total sale del
        // calculo por creditos, y devolver null los dejaba sin bloque.
        $hayAlgo = collect($bloque)->contains(
            fn ($valor) => $valor !== null && $valor !== []
        );

        return $hayAlgo ? $bloque : null;
    }

    /**
     * Un documento del programa, o null si no lo tiene cargado.
     *
     * La ruta puede venir como URL absoluta —el panel guarda asi las subidas,
     * congelando el host del entorno donde se subio— o como ruta del disco
     * publico. Se normaliza aqui para que el sitio reciba siempre algo que
     * pueda abrir.
     *
     * @return array{titulo: string, url: string}|null
     */
    private function documento(string $titulo, ?string $ruta): ?array
    {
        $ruta = trim((string) $ruta);

        if ($ruta === '') {
            return null;
        }

        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            $camino = parse_url($ruta, PHP_URL_PATH) ?? '';

            return [
                'titulo' => $titulo,
                'url' => str_starts_with($camino, '/storage/') ? asset(ltrim($camino, '/')) : $ruta,
            ];
        }

        return ['titulo' => $titulo, 'url' => asset('storage/' . ltrim($ruta, '/'))];
    }
}
