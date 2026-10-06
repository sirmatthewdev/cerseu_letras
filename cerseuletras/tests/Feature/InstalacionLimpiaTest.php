<?php

namespace Tests\Feature;

use App\Models\TipoOferta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Una instalación limpia no publica contenido de otra unidad.
 *
 * Es la regla que el proyecto se dio después de encontrar contenido de la
 * Unidad de Posgrado cinco veces seguidas, cada una en un sitio distinto: un
 * seeder (los diez documentos), una migración (el cronograma de admisión), el
 * respaldo de un controlador (sus títulos), un seeder desincronizado de la base
 * (la misión y los valores) y, el último, el proceso de admisión entero con sus
 * seis convocatorias de diplomados.
 *
 * Las cuatro primeras se encontraron a mano, y la de la migración solo apareció
 * clonando el repositorio en limpio, porque las migraciones corren antes que los
 * seeders y nadie audita contenido ahí. Esta prueba hace ese trabajo sola: monta
 * la base desde cero —migraciones y seeders, como un despliegue nuevo— y recorre
 * todas las tablas buscando el vocabulario que delata a la otra unidad.
 *
 * Si falla, lo que hay que quitar es el contenido, no la prueba. Un texto
 * legítimo del CERSEU no dice «bachiller» ni «diplomado»: su oferta está abierta
 * a toda la comunidad y no exige un grado previo.
 *
 * **Y también comprueba lo contrario: que el contenido propio sí llega.** Esta
 * clase montaba la base como un despliegue nuevo y solo buscaba vocabulario
 * ajeno, así que pasaba en verde sobre una instalación a la que le faltaban los
 * 39 cursos enteros. El fallo se encontró desplegando en la VM —el sitio se
 * construyó con 17 páginas en vez de 80—, y las pruebas de abajo son las que
 * tendrían que haberlo visto antes: una instalación vacía de contenido propio es
 * tan defectuosa como una instalación con contenido de otra unidad.
 */
class InstalacionLimpiaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Vocabulario que solo tiene sentido en el portal de Posgrado.
     *
     * «maestría» y «doctorado» son grados que el CERSEU no ofrece; «bachiller»
     * y «expediente» pertenecen a una admisión con requisitos de titulación;
     * «diplomado» es el nombre de la oferta de la otra unidad.
     */
    private const VOCABULARIO_AJENO = [
        'bachiller',
        'diplomado',
        'maestría',
        'doctorado',
        'entrevista personal',
        'Estudios de Posgrado',
    ];

    /**
     * Columnas donde un texto es contenido publicable. Se excluyen las de
     * identidad y las marcas de tiempo: un slug o una fecha no publican nada.
     */
    private function esColumnaDeTexto(string $tabla, string $columna): bool
    {
        if (in_array($columna, ['id', 'slug', 'created_at', 'updated_at', 'deleted_at'], true)) {
            return false;
        }

        return in_array(
            Schema::getColumnType($tabla, $columna),
            ['string', 'text', 'json'],
            true
        );
    }

    public function test_los_seeders_no_publican_contenido_de_otra_unidad(): void
    {
        $this->seed();

        // El enlace a la web de la unidad hermana sí es legítimo y va en el
        // menú: es un acceso institucional, no contenido suplantado.
        $exentas = ['menu_items', 'migrations', 'users', 'sessions', 'cache', 'jobs'];

        $hallazgos = [];

        foreach (Schema::getTableListing() as $tabla) {
            $tabla = str_contains($tabla, '.') ? substr($tabla, strrpos($tabla, '.') + 1) : $tabla;

            if (in_array($tabla, $exentas, true)) {
                continue;
            }

            $columnas = array_filter(
                Schema::getColumnListing($tabla),
                fn (string $c) => $this->esColumnaDeTexto($tabla, $c)
            );

            if ($columnas === []) {
                continue;
            }

            foreach (DB::table($tabla)->get() as $fila) {
                foreach ($columnas as $columna) {
                    $valor = (string) ($fila->{$columna} ?? '');

                    foreach (self::VOCABULARIO_AJENO as $palabra) {
                        if (mb_stripos($valor, $palabra) !== false) {
                            $hallazgos[] = sprintf(
                                '%s.%s contiene «%s»: %s',
                                $tabla,
                                $columna,
                                $palabra,
                                mb_substr(preg_replace('/\s+/', ' ', $valor), 0, 120)
                            );
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($hallazgos)),
            "Una instalación limpia publica contenido de la Unidad de Posgrado:\n"
                . implode("\n", array_unique($hallazgos))
        );
    }

    public function test_la_admision_queda_con_estructura_y_sin_proceso(): void
    {
        $this->seed(\Database\Seeders\AdmisionSettingSeeder::class);

        // La fila existe para que el panel tenga dónde escribir desde el primer
        // arranque; lo que no trae es el proceso de nadie.
        foreach (TipoOferta::cases() as $tipo) {
            $ajustes = \App\Models\AdmisionSetting::where('tipo', $tipo->value)->first();

            $this->assertNotNull($ajustes, "Falta la fila de admisión de {$tipo->plural()}.");
            $this->assertEmpty($ajustes->pasos ?: []);
            $this->assertEmpty($ajustes->requisitos_lista ?: []);
            $this->assertNull($ajustes->pago_costo);
            $this->assertSame(0, $ajustes->cronogramaItems()->count());
        }
    }

    public function test_sin_titular_propio_la_api_no_inventa_una_convocatoria(): void
    {
        $this->seed(\Database\Seeders\AdmisionSettingSeeder::class);

        // Anunciar una convocatoria sobre una página que dice que el proceso no
        // está publicado es afirmar algo que la propia página desmiente.
        $this->getJson('/api/v1/admision/talleres')
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Admisión · Talleres')
            ->assertJsonPath('data.convocatorias', []);
    }

    /**
     * Los cursos que la migración de sumillas crea en borrador, y que durante un
     * tiempo hicieron creer al seeder de la oferta que ya estaba cargada.
     */
    private const BORRADORES_DE_LA_MIGRACION = 5;

    /** Las siete fichas con sumilla oficial de la Unidad. */
    private const CON_SUMILLA_OFICIAL = [
        'Curso-taller de elaboración de preguntas de opción múltiple',
        'Redacción y Ortografía I',
        'Oratoria y Teatro I',
        'Didática do Português como Língua Pluricêntrica na Formação Docente',
        'Curso-taller: APA sin clichés: más allá de la norma en la producción investigativa',
        'Introducción a la Política de Aristóteles',
        'Investigación cuantitativa, cualitativa y mixta',
    ];

    public function test_una_instalacion_limpia_trae_la_oferta_entera(): void
    {
        // Antes de sembrar ya hay cursos: los cinco borradores que crea la
        // migración de sumillas. Es la situación exacta en la que el seeder se
        // creía cargado y se iba sin hacer nada.
        $this->assertSame(
            self::BORRADORES_DE_LA_MIGRACION,
            \App\Models\Programa::deTipo(TipoOferta::Curso)->count(),
            'Las migraciones ya no dejan los borradores que esta prueba vigila: '
                . 'revisa si la guarda del seeder sigue teniendo sentido.'
        );

        $this->seed();

        $publicados = \App\Models\Programa::deTipo(TipoOferta::Curso)
            ->where('estado', \App\Models\Programa::ESTADO_PUBLICADO)
            ->count();

        $this->assertSame(39, $publicados, 'La programación 2026 no se cargó entera.');
        $this->assertSame(47, \App\Models\AdmisionCronogramaItem::count(), 'Faltan convocatorias.');
        $this->assertGreaterThanOrEqual(20, \App\Models\Docente::where('estado', 1)->count());
    }

    public function test_las_sumillas_oficiales_llegan_a_una_instalacion_limpia(): void
    {
        $this->seed(\Database\Seeders\OfertaCerseuSeeder::class);

        foreach (self::CON_SUMILLA_OFICIAL as $nombre) {
            $curso = \App\Models\Programa::where('nombre', $nombre)->first();

            $this->assertNotNull($curso, "No se sembró «{$nombre}».");

            // El texto que la Unidad entregó, y no la línea que se generaba a
            // partir de las horas y la modalidad. Esas sumillas llegaban por una
            // migración que en una instalación limpia no tiene ninguna ficha que
            // actualizar, así que viven también en el fichero de datos.
            $this->assertDoesNotMatchRegularExpression(
                '/^Curso de \d+ horas académicas en modalidad/',
                (string) $curso->sumilla,
                "«{$nombre}» se quedó con la sumilla generada en vez de la oficial."
            );
            $this->assertGreaterThan(300, mb_strlen((string) $curso->sumilla));
        }
    }

    public function test_sembrar_la_oferta_dos_veces_no_la_duplica(): void
    {
        $this->seed(\Database\Seeders\OfertaCerseuSeeder::class);
        $this->seed(\Database\Seeders\OfertaCerseuSeeder::class);

        $this->assertSame(39, \App\Models\Programa::deTipo(TipoOferta::Curso)
            ->where('estado', \App\Models\Programa::ESTADO_PUBLICADO)
            ->count());
        $this->assertSame(47, \App\Models\AdmisionCronogramaItem::count());
    }
}
