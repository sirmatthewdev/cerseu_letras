<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Programa extends Model
{
    use \App\Models\Concerns\InvalidatesSearchIndex, SoftDeletes;

    protected $fillable = [
        'grado',
        'nombre',
        'mencion',
        'modalidad',
        'vacantes',
        'duracion',
        'creditos',
        'sesiones',
        'modulos',
        // Denominación del título que otorga: rótulo y contenido, ambos
        // editables y ambos opcionales (ver getDenominacionOtorgaAttribute).
        'grado_otorga',
        'grado_otorga_label',
        'objetivos_academicos',
        'perfil_ingresante',
        'perfil_graduado',
        'plan_url',
        'horario_url',
        'brochure_url',
        'admision_pdf_url',
        'horas_academicas',
        'fecha_limite_inscripcion',
        'inversion_economica',
        'costo_por_credito',
        'semestres_inversion',
        'por_que_text',
        'sumilla',
        'plan_estudios',
        'is_active',
        'estado',
        'slug',
        'imagen'
    ];

    /** Publicado: visible en listados y con su ficha completa. */
    public const ESTADO_PUBLICADO = 'publicado';

    /** Próximamente: se anuncia en los listados, pero su ficha aún no tiene detalle. */
    public const ESTADO_PROXIMAMENTE = 'proximamente';

    /** Borrador: no existe de cara al público (404). */
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADOS = [
        self::ESTADO_PUBLICADO => [
            'label' => 'Publicado',
            'ayuda' => 'Visible en los listados y con su ficha completa.',
            'badge' => 'bg-green-100 text-green-800',
            'punto' => 'bg-green-600',
        ],
        self::ESTADO_PROXIMAMENTE => [
            'label' => 'Próximamente',
            'ayuda' => 'Aparece en los listados con la etiqueta «Próximamente»; su página anuncia que pronto habrá información.',
            'badge' => 'bg-amber-100 text-amber-800',
            'punto' => 'bg-amber-500',
        ],
        self::ESTADO_BORRADOR => [
            'label' => 'Borrador',
            'ayuda' => 'Oculto por completo: no se lista y su URL responde 404.',
            'badge' => 'bg-gray-100 text-gray-800',
            'punto' => 'bg-gray-500',
        ],
    ];

    /**
     * Denominaciones admitidas para el responsable académico (Obs. N.º 1).
     * La primera es la que se aplica cuando el programa no ha elegido ninguna.
     */
    public const DENOMINACIONES_COORDINADOR = ['Coordinador', 'Coordinadora'];

    protected $casts = [
        'vacantes' => 'integer',
        'duracion' => 'integer',
        'creditos' => 'integer',
        'horas_academicas' => 'integer',
        'is_active' => 'boolean',
        'plan_estudios' => 'array',
        'objetivos_academicos' => 'array',
        'perfil_ingresante' => 'array',
        'perfil_graduado' => 'array',
        'inversion_economica' => 'array',
        'costo_por_credito' => 'integer',
        'semestres_inversion' => 'array',
    ];

    // Relaciones
    public function docentes()
    {
        return $this->belongsToMany(Docente::class, 'docente_programa')
            ->withPivot('es_coordinador', 'coordinador_denominacion', 'rol', 'orden')
            ->withTimestamps()
            ->orderBy('docente_programa.orden');
    }

    public function testimonios()
    {
        return $this->hasMany(Testimonio::class);
    }

    // Scopes

    /**
     * Programas publicados. Es el scope que usan las páginas públicas: mantiene
     * el nombre histórico para no tocar sus llamadas, pero ahora se apoya en
     * `estado` en lugar del booleano.
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', self::ESTADO_PUBLICADO);
    }

    /**
     * Programas alcanzables por el público: los publicados y los anunciados
     * como próxima oferta. Los borradores quedan fuera.
     */
    public function scopeVisibles($query)
    {
        return $query->whereIn('estado', [self::ESTADO_PUBLICADO, self::ESTADO_PROXIMAMENTE]);
    }

    public function scopeProximamente($query)
    {
        return $query->where('estado', self::ESTADO_PROXIMAMENTE);
    }

    /**
     * Orden de los listados públicos: primero la oferta vigente y al final la
     * que aún se anuncia, y dentro de cada grupo por nombre.
     */
    public function scopeOrdenPublicacion($query)
    {
        return $query
            ->orderByRaw("CASE WHEN estado = ? THEN 0 ELSE 1 END", [self::ESTADO_PUBLICADO])
            ->orderBy('nombre');
    }

    public function scopeTalleres($query)
    {
        return $query->where('grado', TipoOferta::Taller->grado());
    }

    public function scopeCursos($query)
    {
        return $query->where('grado', TipoOferta::Curso->grado());
    }

    public function scopeEspecializaciones($query)
    {
        return $query->where('grado', TipoOferta::Especializacion->grado());
    }

    public function scopeDeTipo($query, TipoOferta $tipo)
    {
        return $query->where('grado', $tipo->grado());
    }

    public function esTaller(): bool
    {
        return $this->grado === TipoOferta::Taller->grado();
    }

    public function esCurso(): bool
    {
        return $this->grado === TipoOferta::Curso->grado();
    }

    /**
     * Lo que se anuncia de esta oferta, ya formateado: «12 sesiones», «6 meses»…
     *
     * Cada tipo se mide distinto (ver TipoOferta::medidas). Se omite lo que no
     * tenga valor, de modo que una ficha a medio llenar no pinta chips vacíos.
     *
     * @return list<string>
     */
    public function medidasFormateadas(): array
    {
        $tipo = $this->tipoOferta();

        if (! $tipo) {
            return [];
        }

        $medidas = [];

        foreach ($tipo->medidas() as $campo => $unidad) {
            if ($valor = $this->{$campo}) {
                $medidas[] = $valor . ' ' . $unidad;
            }
        }

        return $medidas;
    }

    /**
     * URL pública de la ficha, bajo el módulo que le corresponde.
     *
     * Cada tipo tiene su propio prefijo (/talleres/…, /cursos/…), así que la
     * ruta no se puede escribir a mano desde las vistas sin repetir en cada
     * una la decisión de a qué módulo pertenece la fila.
     */
    public function getUrlAttribute(): string
    {
        $tipo = $this->tipoOferta() ?? TipoOferta::Curso;

        // Ruta, no URL absoluta: el sitio publico es estatico y Laravel ya no
        // conoce estas direcciones. `route()` daba un error de ruta inexistente
        // y se llevaba por delante al buscador, que las compone desde aqui.
        return \App\Support\DestinosPublicos::programa($tipo, $this->slug);
    }

    /** El tipo de oferta al que pertenece, o null si el grado es de antes. */
    public function tipoOferta(): ?TipoOferta
    {
        foreach (TipoOferta::cases() as $tipo) {
            if ($tipo->grado() === $this->grado) {
                return $tipo;
            }
        }

        return null;
    }

    // Accessors
    public function getTituloCompletoAttribute()
    {
        if ($this->mencion) {
            return "{$this->nombre} con mención en {$this->mencion}";
        }
        return $this->nombre;
    }

    public function getDuracionFormateadaAttribute()
    {
        if (!$this->duracion) return null;
        // Un taller se mide en semanas y un curso en meses; ninguno de los dos
        // se organiza por semestres, que era la unidad de las maestrías.
        $unidad = $this->esTaller() ? 'semanas' : 'meses';
        return "{$this->duracion} {$unidad}";
    }

    public function getTituloAttribute()
    {
        return $this->titulo_completo;
    }

    public function getTipoAttribute()
    {
        return match($this->grado) {
            'Taller' => 'taller',
            'Curso'  => 'curso',
            default  => strtolower($this->grado),
        };
    }

    /**
     * Rótulo con el que se presenta el título que otorga el programa.
     *
     * Antes el texto se generaba aquí («Magíster en …») y la ficha lo anunciaba
     * siempre como «Grado que otorga». En un diplomado eso es incorrecto —no es
     * un grado académico— y no había forma de cambiarlo. Ahora tanto el rótulo
     * como el contenido salen de la base de datos y pueden quedar vacíos.
     *
     * Devuelve null cuando no hay nada que mostrar, para que la vista pueda
     * omitir el bloque entero en lugar de dejar una etiqueta suelta.
     *
     * @return array{label: ?string, texto: string}|null
     */
    public function getDenominacionOtorgaAttribute(): ?array
    {
        $texto = trim((string) $this->grado_otorga);

        if ($texto === '') {
            return null;
        }

        $label = trim((string) $this->grado_otorga_label);

        return [
            'label' => $label !== '' ? $label : null,
            'texto' => $texto,
        ];
    }

    /**
     * La denominación ya compuesta («Otorga: Diploma en …»), o null si el
     * programa aún no la tiene definida.
     */
    public function getDenominacionOtorgaTextoAttribute(): ?string
    {
        $denominacion = $this->denominacion_otorga;

        if ($denominacion === null) {
            return null;
        }

        return $denominacion['label']
            ? $denominacion['label'] . ': ' . $denominacion['texto']
            : $denominacion['texto'];
    }

    // Coordinación académica

    /**
     * Docente marcado como responsable del programa, si lo hay.
     */
    public function getCoordinadorAttribute(): ?Docente
    {
        return $this->docentes->firstWhere('pivot.es_coordinador', 1);
    }

    /**
     * Denominación del responsable académico: «Coordinador» o «Coordinadora»
     * según se haya configurado en el programa. Sin valor guardado se mantiene
     * la denominación que el sitio venía usando.
     */
    public static function denominacionCoordinador(?string $valor): string
    {
        $valor = trim((string) $valor);

        return in_array($valor, self::DENOMINACIONES_COORDINADOR, true)
            ? $valor
            : self::DENOMINACIONES_COORDINADOR[0];
    }

    // Inversión económica

    /**
     * Semestres (o módulos) con sus importes ya calculados.
     *
     * Punto único del cálculo: antes vivía repetido en las tres plantillas de
     * inversión y otra vez en programas/show, con el riesgo de que dejaran de
     * coincidir entre sí.
     *
     * @return array<int, array{numero:int, matricula:int, creditos:int, costo_semestre:int, cuota_mensual:float}>
     */
    public function getSemestresCalculadosAttribute(): array
    {
        $costoCredito = (int) ($this->costo_por_credito ?? 0);
        $filas = [];

        foreach ((array) ($this->semestres_inversion ?? []) as $i => $sem) {
            $matricula = (int) ($sem['matricula'] ?? 0);
            $creditos = (int) ($sem['creditos'] ?? 0);
            $costoSemestre = $creditos * $costoCredito;

            $filas[] = [
                'numero' => $i + 1,
                'matricula' => $matricula,
                'creditos' => $creditos,
                'costo_semestre' => $costoSemestre,
                // Referencia orientativa: el semestre se fracciona en 4 pagos.
                // Se fuerza a float: en PHP la división exacta devuelve int, y
                // el tipo cambiaría según los importes de cada programa.
                'cuota_mensual' => (float) ($costoSemestre / 4),
            ];
        }

        return $filas;
    }

    /**
     * Costo total del programa.
     *
     * Prioriza el importe cerrado que se haya cargado en `inversion_economica`
     * (así lo tienen los diplomados) y, si no existe, lo calcula sumando
     * matrículas y créditos.
     */
    public function getCostoTotalAttribute(): ?int
    {
        $cerrado = $this->inversion_economica['costo_total'] ?? null;

        if (is_numeric($cerrado)) {
            return (int) $cerrado;
        }

        $filas = $this->semestres_calculados;

        if (empty($filas)) {
            return null;
        }

        return (int) array_sum(array_map(
            fn ($f) => $f['matricula'] + $f['costo_semestre'],
            $filas
        ));
    }

    /**
     * Modalidades de pago de los derechos de enseñanza, ya normalizadas
     * (Obs. N.º 2).
     *
     * Punto único de lectura para la plantilla: resuelve aquí la convivencia
     * entre el formato estructurado que carga el panel y las `cuotas` planas
     * que quedaron de la versión anterior, para que la vista no tenga que
     * conocer las dos formas. Las cuotas sin monto ni fecha se descartan: son
     * filas que el panel deja al añadir y no completar.
     *
     * @return array<int, array{nombre:string, cuotas:array<int, array{etiqueta:string, monto:?float, fecha:?string}>}>
     */
    public function getModalidadesDePagoAttribute(): array
    {
        $inversion = (array) ($this->inversion_economica ?? []);

        $modalidades = !empty($inversion['modalidades'])
            ? (array) $inversion['modalidades']
            // Respaldo: las cuotas sueltas de antes se presentan como una única
            // modalidad, sin nombre, para no dejar de mostrar importes ya cargados.
            : [['nombre' => null, 'cuotas' => (array) ($inversion['cuotas'] ?? [])]];

        $normalizadas = [];

        foreach ($modalidades as $modalidad) {
            if (!is_array($modalidad)) {
                continue;
            }

            $cuotas = [];

            foreach ((array) ($modalidad['cuotas'] ?? []) as $i => $cuota) {
                if (!is_array($cuota)) {
                    continue;
                }

                $monto = $cuota['monto'] ?? null;
                $fecha = trim((string) ($cuota['fecha'] ?? ''));

                if (($monto === null || $monto === '') && $fecha === '') {
                    continue;
                }

                $etiqueta = trim((string) ($cuota['etiqueta'] ?? ''));

                $cuotas[] = [
                    'etiqueta' => $etiqueta !== '' ? $etiqueta : 'Cuota ' . ($cuota['numero'] ?? $i + 1),
                    'monto' => is_numeric($monto) ? (float) $monto : null,
                    'fecha' => $fecha !== '' ? $fecha : null,
                ];
            }

            if ($cuotas === []) {
                continue;
            }

            $nombre = trim((string) ($modalidad['nombre'] ?? ''));

            $normalizadas[] = [
                // Sin nombre guardado se deduce del número de cuotas, que es
                // exactamente lo que distingue una modalidad de la otra.
                'nombre' => $nombre !== '' ? $nombre : (count($cuotas) === 1 ? 'Pago único' : 'Pago fraccionado'),
                'cuotas' => $cuotas,
            ];
        }

        return $normalizadas;
    }

    /**
     * Condiciones de pago como lista de puntos.
     *
     * Antes se armaban a partir de tres campos sueltos —`modalidades_pago` en
     * texto libre, `descuentos` y `observaciones`—, así que solo cabían tres
     * puntos y cada uno con un significado fijo. Ahora se guardan en
     * `condiciones` como una lista que se administra entera desde el panel.
     *
     * Los tres campos antiguos siguen sirviendo de respaldo mientras un
     * diplomado no tenga la lista cargada, para no dejar de mostrar lo que ya
     * estuviera publicado.
     *
     * @return array<int, string>
     */
    public function getCondicionesDePagoAttribute(): array
    {
        $inversion = (array) ($this->inversion_economica ?? []);

        if (!empty($inversion['condiciones'])) {
            $lista = array_map(
                fn ($c) => trim((string) (is_array($c) ? ($c['texto'] ?? '') : $c)),
                (array) $inversion['condiciones']
            );

            return array_values(array_filter($lista, fn ($c) => $c !== ''));
        }

        // Respaldo con el formato anterior.
        $lista = [];

        $modalidades = array_filter(array_map('trim', (array) ($inversion['modalidades_pago'] ?? [])));
        if ($modalidades !== []) {
            $lista[] = 'Modalidades de pago: ' . implode(', ', $modalidades) . '.';
        }

        foreach (['descuentos', 'observaciones'] as $clave) {
            $valor = trim((string) ($inversion[$clave] ?? ''));

            if ($valor !== '') {
                $lista[] = $valor;
            }
        }

        return $lista;
    }

    /**
     * Etiqueta del periodo ("Módulo 1").
     *
     * Talleres y cursos se organizan por módulos. La variante por semestres
     * era de las maestrías y doctorados, que el CERSEU no ofrece.
     */
    public function etiquetaPeriodo(int $numero): string
    {
        return 'Módulo ' . $numero;
    }

    // Estado de publicación

    public function getEsPublicadoAttribute(): bool
    {
        return $this->estado === self::ESTADO_PUBLICADO;
    }

    public function getEsProximamenteAttribute(): bool
    {
        return $this->estado === self::ESTADO_PROXIMAMENTE;
    }

    public function getEsBorradorAttribute(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado]['label'] ?? self::ESTADOS[self::ESTADO_BORRADOR]['label'];
    }

    public function getEstadoBadgeAttribute(): string
    {
        return self::ESTADOS[$this->estado]['badge'] ?? self::ESTADOS[self::ESTADO_BORRADOR]['badge'];
    }

    public function getEstadoPuntoAttribute(): string
    {
        return self::ESTADOS[$this->estado]['punto'] ?? self::ESTADOS[self::ESTADO_BORRADOR]['punto'];
    }

    /**
     * URL utilizable del brochure: admite tanto una URL absoluta como una ruta
     * relativa guardada en el disco `public` (según cómo se haya subido).
     * Devuelve null cuando el programa aún no tiene brochure, para poder ocultar
     * el botón sin romper el diseño.
     */
    public function getBrochureLinkAttribute(): ?string
    {
        $valor = trim((string) $this->brochure_url);

        if ($valor === '') {
            return null;
        }

        if (str_starts_with($valor, 'http://') || str_starts_with($valor, 'https://')) {
            $ruta = parse_url($valor, PHP_URL_PATH) ?? '';

            // El panel guarda la subida como URL absoluta (`asset()`), lo que
            // congela el host del entorno donde se subió: un brochure cargado en
            // local apuntaría a localhost al publicar. Si el archivo es nuestro
            // (/storage/...), se reconstruye contra el host actual; los enlaces
            // externos (Drive, etc.) se respetan tal cual.
            if (str_starts_with($ruta, '/storage/')) {
                return asset(ltrim($ruta, '/'));
            }

            return $valor;
        }

        return asset('storage/' . ltrim($valor, '/'));
    }


    public function getImagenUrlAttribute()
    {
        /*
         * Sin imagen propia: una del campus, auto-alojada. Antes se pedían a
         * Unsplash: media pantalla de peso y una dependencia externa en la ruta
         * crítica.
         *
         * Una por tipo, y las tres distintas. Antes había dos —la de curso y la
         * de taller, que hacía también de especialización—, de modo que dos de
         * los tres tipos enseñaban la misma. Con una cada uno, la fotografía
         * acompaña a la etiqueta que la tarjeta lleva encima en vez de
         * contradecirla.
         *
         * Se comparan con `->grado()` y no con la cadena escrita a mano: es lo
         * que guarda la columna y lo que usan los `scope` de este mismo modelo.
         */
        if (!$this->imagen) {
            return match ($this->grado) {
                TipoOferta::Curso->grado() => asset('images/programa-curso.webp'),
                TipoOferta::Especializacion->grado() => asset('images/campus-aerea-2.webp'),
                default => asset('images/programa-taller.webp'),
            };
        }
        
        // Si ya es una URL completa (http:// o https://)
        // Esto cubre: URLs externas, Unsplash, etc.
        if (str_starts_with($this->imagen, 'http://') || str_starts_with($this->imagen, 'https://')) {
            return $this->imagen;
        }
        
        // Para rutas locales (ahora siempre serán relativas como 'documents/...')
        // asset('storage/' + ruta) generará: https://cerseuletras.../storage/documents/...
        return asset('storage/' . $this->imagen);
    }
    

    // Mutators
    protected static function boot()
    {
        parent::boot();

        // `estado` es la única fuente de verdad; `is_active` se mantiene en
        // sincronía para no romper consultas o informes que aún lo lean.
        static::saving(function ($programa) {
            $atributos = $programa->getAttributes();

            // Compatibilidad hacia atrás: los seeders y el código anterior al
            // campo `estado` solo envían `is_active`. Sin esto, todo lo que se
            // creara por esa vía quedaría en borrador y desaparecería del sitio.
            if (!array_key_exists('estado', $atributos) || $programa->estado === null) {
                $programa->estado = !empty($atributos['is_active'])
                    ? self::ESTADO_PUBLICADO
                    : self::ESTADO_BORRADOR;
            }

            if (!array_key_exists($programa->estado, self::ESTADOS)) {
                $programa->estado = self::ESTADO_BORRADOR;
            }

            $programa->is_active = $programa->estado === self::ESTADO_PUBLICADO;
        });

        static::creating(function ($programa) {
            if (empty($programa->slug)) {
                $baseName = $programa->nombre;
                if ($programa->mencion) {
                    $baseName .= ' ' . $programa->mencion;
                }
                $programa->slug = Str::slug($baseName);
            }
        });

        static::updating(function ($programa) {
            if (empty($programa->slug)) {
                $baseName = $programa->nombre;
                if ($programa->mencion) {
                    $baseName .= ' ' . $programa->mencion;
                }
                $programa->slug = Str::slug($baseName);
            }
        });
    }
}
