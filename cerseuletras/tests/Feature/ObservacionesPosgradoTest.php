<?php

namespace Tests\Feature;

use App\Models\CronogramaAdmision;
use App\Models\Programa;
use App\Models\User;
use App\Filament\Pages\CronogramaDeAdmision;
use App\Filament\Resources\Programas\Pages\ListProgramas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cubre las observaciones del documento de requerimientos de Posgrado
 * («Ajustes para la página web de Diplomados»).
 *
 * Se comprueban contra la API, que es por donde estos datos llegan al sitio
 * desde que el público es estático. Lo que era marcado —el orden de dos
 * botones, qué filtro arranca marcado— lo comprueba ahora sitio/e2e sobre el
 * sitio construido.
 */
class ObservacionesPosgradoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /**
     * Obs. N.º 1 — la portada lleva a la oferta y a su inscripción.
     *
     * La observación original pedía que los accesos apuntaran a diplomados,
     * que es lo que entonces ofrecía la Unidad. Se conserva la intención
     * —que el visitante aterrice en algo que existe— con el destino que hoy
     * tiene contenido: Cursos, la única de las tres secciones con programas
     * publicados. Talleres y Especializaciones siguen vacías.
     */
    public function test_la_portada_ofrece_los_accesos_a_la_oferta(): void
    {
        $this->seed(\Database\Seeders\SiteSettingsSeeder::class);
        \App\Models\SiteSetting::clearCache();

        $portada = $this->getJson('/api/v1/sitio')->assertOk()->json('data.portada');
        $acciones = collect($portada['acciones']);

        // Los dos accesos del hero llevan a algo que existe, y sus destinos
        // son rutas del sitio y no direcciones de Laravel.
        $this->assertSame('Ver cursos', $acciones[0]['texto']);
        $this->assertStringStartsWith('/', $acciones[0]['url']);
        $this->assertSame('Cómo inscribirte', $acciones[1]['texto']);
        $this->assertStringStartsWith('/', $acciones[1]['url']);
    }

    /** Obs. N.º 2 — la sección del cronograma es editable y ocultable. */
    public function test_el_cronograma_de_admision_se_administra_y_se_puede_ocultar(): void
    {
        // La sección es un registro único. La migración ya no lo siembra —crea
        // estructura, no contenido—, así que la prueba monta el suyo.
        $cronograma = CronogramaAdmision::firstOrCreate([], ['is_visible' => true]);
        $cronograma->update([
            'eyebrow' => 'Proceso de Admisión de Diplomados 2026-II',
            'titulo' => 'Cronograma de Admisión',
            'boton_texto' => 'Iniciar inscripción',
            'boton_url' => 'https://ejemplo.test/inscripcion',
            'is_visible' => true,
        ]);
        $cronograma->pasos()->delete();
        $cronograma->pasos()->create([
            'titulo' => 'Inscripción de postulantes',
            'fecha_inicio' => '5 ene',
            'fecha_fin' => '02 abr',
            'detalle' => '+ Envío de expediente',
            'icono' => 'inscripcion',
            'orden' => 0,
            'destacado' => true,
            'is_visible' => true,
        ]);
        CronogramaAdmision::clearCache();

        $inscripcion = $this->getJson('/api/v1/sitio')->assertOk()->json('data.inscripcion');

        $this->assertSame('Proceso de Admisión de Diplomados 2026-II', $inscripcion['eyebrow']);
        $this->assertSame('https://ejemplo.test/inscripcion', $inscripcion['boton']['url']);
        $this->assertSame('Inscripción de postulantes', $inscripcion['pasos'][0]['titulo']);
        $this->assertSame('5 ene - 02 abr', $inscripcion['pasos'][0]['fecha']);

        // Ocultar la sección completa cuando no hay convocatoria activa. La
        // API devuelve null y el sitio omite el bloque entero, en vez de
        // pintar un título sobre un hueco.
        $cronograma->update(['is_visible' => false]);
        CronogramaAdmision::clearCache();

        $this->assertNull($this->getJson('/api/v1/sitio')->json('data.inscripcion'));
    }

    /** Obs. N.º 2 — el panel guarda encabezado, etapas, orden y botón. */
    public function test_el_panel_guarda_el_cronograma_completo(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CronogramaDeAdmision::class)
            ->fillForm([
                'eyebrow' => 'Proceso de Admisión de Diplomados 2026-II',
                'titulo' => 'Cronograma de Diplomados',
                'boton_texto' => 'Postular',
                'boton_url' => '/diplomados/admision',
                'is_visible' => true,
                'pasos' => [
                    ['titulo' => 'Examen', 'fecha_inicio' => '06 de abril', 'publico' => 'Maestrías',
                     'icono' => 'examen', 'destacado' => false, 'is_visible' => true],
                    ['titulo' => 'Resultados', 'fecha_inicio' => '09 de abril', 'detalle' => 'Lista oficial',
                     'icono' => 'check', 'destacado' => true, 'is_visible' => true],
                ],
            ])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $cronograma = CronogramaAdmision::first();
        $this->assertSame('Cronograma de Diplomados', $cronograma->titulo);
        $this->assertTrue((bool) $cronograma->is_visible);

        $pasos = $cronograma->pasos()->get();
        $this->assertCount(2, $pasos);
        // El orden es el de la lista del formulario.
        $this->assertSame('Examen', $pasos[0]->titulo);
        $this->assertSame('Resultados', $pasos[1]->titulo);
        $this->assertLessThan($pasos[1]->orden, $pasos[0]->orden);
        $this->assertTrue((bool) $pasos[1]->destacado);
    }

    /**
     * El icono ya no puede ser inválido.
     *
     * Antes era un campo de texto y había que sanearlo al guardar —escribir
     * cualquier cosa dejaba el paso sin icono—. Ahora sale de una lista
     * cerrada, así que lo que se comprueba es que la lista sea la del modelo.
     */
    public function test_el_icono_del_paso_sale_del_catalogo_del_modelo(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CronogramaDeAdmision::class)
            ->fillForm([
                'pasos' => [['titulo' => 'Etapa', 'icono' => 'no-existe', 'is_visible' => true]],
            ])
            ->call('guardar')
            ->assertHasFormErrors();
    }

    /** Obs. N.º 3 — filtros reordenados y "Diplomados" activo por defecto. */
    public function test_los_filtros_priorizan_talleres(): void
    {
        // El orden de los filtros sale del enum, no de la plantilla: la
        // portada los recorre tal como llegan. Cuál arranca marcado lo decide
        // el sitio —el primero CON oferta, para no abrir en un vacío— y lo
        // comprueba sitio/e2e.
        $tipos = collect($this->getJson('/api/v1/tipos-oferta')->assertOk()->json('data'))
            ->pluck('slug')
            ->all();

        $this->assertSame(['talleres', 'cursos', 'especializaciones'], $tipos);
    }

    /** Obs. N.º 4 — botón Brochure antes de Postular, oculto si no hay archivo. */
    public function test_el_brochure_aparece_antes_de_postular_y_se_oculta_sin_archivo(): void
    {
        $programa = Programa::create([
            'grado' => 'Taller',
            'nombre' => 'Diplomado de Prueba',
            'modalidad' => 'Virtual',
            'duracion' => 2,
            'creditos' => 12,
            'is_active' => true,
            'brochure_url' => 'documents/brochure.pdf',
        ]);

        $documentos = $this->getJson('/api/v1/programas/' . $programa->slug)
            ->assertOk()
            ->json('data.documentos');

        $brochure = collect($documentos)->firstWhere('titulo', 'Brochure');
        $this->assertNotNull($brochure, 'El brochure no llega a la ficha');
        $this->assertStringContainsString('storage/documents/brochure.pdf', $brochure['url']);

        // Sin brochure no llega nada: la ficha no puede pintar un botón que
        // no lleve a ningún archivo.
        $programa->update(['brochure_url' => null]);

        $this->assertSame(
            [],
            $this->getJson('/api/v1/programas/' . $programa->slug)->json('data.documentos'),
        );
    }

    /** Obs. N.º 5 — buscador: resultados con categoría, relevancia y vacío. */
    public function test_el_buscador_devuelve_resultados_por_relevancia(): void
    {
        Programa::create([
            'grado' => 'Taller', 'nombre' => 'Taller en Lingüística Forense',
            'modalidad' => 'Virtual', 'duracion' => 2, 'creditos' => 12, 'is_active' => true,
        ]);

        // La búsqueda ya no la resuelve el servidor: la API entrega el índice
        // entero y busca el navegador, que es lo único que puede hacer un
        // sitio estático. Aquí se comprueba que el índice llegue completo y
        // con lo que la puntuación necesita; que ordene y agrupe bien lo
        // comprueba sitio/e2e.
        $indice = collect($this->getJson('/api/v1/buscador')->assertOk()->json('data'));

        $entrada = $indice->firstWhere('titulo', 'Taller en Lingüística Forense');

        $this->assertNotNull($entrada, 'El taller no aparece en el índice');
        $this->assertSame('Talleres', $entrada['categoria']);
        $this->assertStringStartsWith('/talleres/', $entrada['url']);

        // Normalizado en el servidor: es lo que permite que «linguistica»
        // encuentre «Lingüística» sin rehacer el trabajo en cada navegador.
        $this->assertSame('taller en linguistica forense', $entrada['t']);

        // Y la página de admisión sigue en el índice, que es lo que más se
        // busca.
        $this->assertNotNull($indice->firstWhere('url', '/admision'));
    }

    /** Obs. N.º 4 — el enlace del brochure no depende del entorno donde se subió. */
    public function test_el_brochure_se_reancla_al_host_actual(): void
    {
        $programa = new Programa();

        // Subido en local: el host quedó congelado en la base de datos.
        $programa->brochure_url = 'http://127.0.0.1:8123/storage/documents/folleto.pdf';
        $this->assertSame(asset('storage/documents/folleto.pdf'), $programa->brochure_link);

        // Ruta relativa: se resuelve igual.
        $programa->brochure_url = 'documents/folleto.pdf';
        $this->assertSame(asset('storage/documents/folleto.pdf'), $programa->brochure_link);

        // Enlace externo: se respeta sin tocarlo.
        $externo = 'https://drive.google.com/file/d/abc/view';
        $programa->brochure_url = $externo;
        $this->assertSame($externo, $programa->brochure_link);

        // Vacío: no hay botón.
        $programa->brochure_url = '';
        $this->assertNull($programa->brochure_link);
    }

    /** Inversión: los importes salen del panel y hay un único cálculo. */
    public function test_la_inversion_se_calcula_desde_los_datos_del_programa(): void
    {
        $programa = Programa::create([
            'grado' => 'Curso', 'nombre' => 'Maestría con Tarifas',
            'modalidad' => 'Presencial', 'duracion' => 4, 'creditos' => 30,
            'estado' => Programa::ESTADO_PUBLICADO,
            'costo_por_credito' => 100,
            'semestres_inversion' => [
                ['matricula' => 300, 'creditos' => 10],
                ['matricula' => 400, 'creditos' => 20],
            ],
        ]);

        $filas = $programa->semestres_calculados;
        $this->assertCount(2, $filas);
        $this->assertSame(1000, $filas[0]['costo_semestre']);   // 10 créditos × 100
        $this->assertSame(250.0, $filas[0]['cuota_mensual']);   // dividido en 4
        // Total = (300 + 1000) + (400 + 2000)
        $this->assertSame(3700, $programa->costo_total);

        // La ficha recibe ese mismo total ya calculado, sin rehacerlo por su
        // cuenta: la regla vive en el modelo y no puede duplicarse en el sitio.
        $this->assertEquals(
            3700,
            $this->getJson('/api/v1/programas/' . $programa->slug)->json('data.inversion.costo_total'),
        );
    }

    /** Inversión: un importe cerrado (caso diplomados) tiene prioridad. */
    public function test_el_costo_total_cerrado_manda_sobre_el_calculo(): void
    {
        $programa = Programa::create([
            'grado' => 'Taller', 'nombre' => 'Diplomado con Precio Cerrado',
            'modalidad' => 'Virtual', 'duracion' => 2, 'creditos' => 12,
            'estado' => Programa::ESTADO_PUBLICADO,
            'costo_por_credito' => 120,
            'semestres_inversion' => [['matricula' => 200, 'creditos' => 12]],
            'inversion_economica' => ['costo_total' => 3280],
        ]);

        $this->assertSame(3280, $programa->costo_total);
    }

    /** Inversión: sin tarifas cargadas se avisa en vez de mostrar ceros. */
    public function test_sin_tarifas_la_ficha_dice_por_definir(): void
    {
        $programa = Programa::create([
            'grado' => 'Curso', 'nombre' => 'Maestría sin Tarifas',
            'modalidad' => 'Presencial', 'duracion' => 4, 'creditos' => 30,
            'estado' => Programa::ESTADO_PUBLICADO,
            'costo_por_credito' => null,
            'semestres_inversion' => [],
        ]);

        $this->assertNull($programa->costo_total);

        // Sin tarifas no llega bloque de inversión: la ficha lo omite en vez
        // de pintar ceros o un «por definir» que no dice nada.
        $this->assertNull(
            $this->getJson('/api/v1/programas/' . $programa->slug)->json('data.inversion'),
        );
    }

    /** Estados: borrador responde 404 aunque se conozca la URL. */
    public function test_un_programa_en_borrador_responde_404(): void
    {
        $programa = Programa::create([
            'grado' => 'Taller', 'nombre' => 'Diplomado Secreto',
            'modalidad' => 'Virtual', 'duracion' => 2, 'creditos' => 12,
            'estado' => Programa::ESTADO_BORRADOR,
        ]);

        // No hay ficha que generar: el sitio se construye contra la API.
        $this->getJson('/api/v1/programas/' . $programa->slug)->assertNotFound();

        // Tampoco aparece en listados ni en el índice del buscador.
        $this->getJson('/api/v1/programas')->assertJsonMissing(['nombre' => 'Diplomado Secreto']);
        $this->getJson('/api/v1/buscador')->assertJsonMissing(['titulo' => 'Diplomado Secreto']);

        // `is_active` se mantiene sincronizado con el estado.
        $this->assertFalse($programa->fresh()->is_active);
    }

    /** Estados: "próximamente" se anuncia y su ficha muestra el aviso. */
    public function test_un_programa_proximamente_muestra_el_aviso(): void
    {
        $programa = Programa::create([
            'grado' => 'Taller', 'nombre' => 'Diplomado en Archivística',
            'modalidad' => 'Virtual', 'duracion' => 2, 'creditos' => 12,
            'estado' => Programa::ESTADO_PROXIMAMENTE,
        ]);

        // Sigue teniendo ficha, y llega marcado: es el estado lo que le dice
        // al sitio que avise en lugar de mostrar una ficha vacía.
        $ficha = $this->getJson('/api/v1/programas/' . $programa->slug)->assertOk()->json('data');

        $this->assertSame('proximamente', $ficha['estado']);
        $this->assertSame('Diplomado en Archivística', $ficha['nombre']);

        // Y sí se anuncia en el listado de su módulo.
        $this->getJson('/api/v1/programas?tipo=talleres')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Diplomado en Archivística']);

        $this->assertFalse($programa->fresh()->is_active);
    }

    /** Estados: publicado mantiene el comportamiento completo de siempre. */
    public function test_un_programa_publicado_muestra_su_ficha_completa(): void
    {
        $programa = Programa::create([
            'grado' => 'Taller', 'nombre' => 'Diplomado Vigente',
            'modalidad' => 'Virtual', 'duracion' => 2, 'creditos' => 12,
            'estado' => Programa::ESTADO_PUBLICADO,
        ]);

        $ficha = $this->getJson('/api/v1/programas/' . $programa->slug)->assertOk()->json('data');

        $this->assertSame('Diplomado Vigente', $ficha['nombre']);
        $this->assertSame('publicado', $ficha['estado']);

        $this->assertTrue($programa->fresh()->is_active);
    }

    /** Estados: un valor inválido cae a borrador en vez de dejar el programa visible. */
    public function test_un_estado_invalido_cae_a_borrador(): void
    {
        $programa = Programa::create([
            'grado' => 'Curso', 'nombre' => 'Maestría Rara',
            'modalidad' => 'Presencial', 'duracion' => 4, 'creditos' => 40,
            'estado' => 'lo-que-sea',
        ]);

        $this->assertSame(Programa::ESTADO_BORRADOR, $programa->fresh()->estado);
        $this->get($programa->url)->assertNotFound();
    }

    /** Estados: el atajo del panel alterna entre publicado y borrador. */
    public function test_el_panel_alterna_publicado_y_borrador(): void
    {
        $programa = Programa::create([
            'grado' => 'Curso', 'nombre' => 'Maestría Toggle',
            'modalidad' => 'Presencial', 'duracion' => 4, 'creditos' => 40,
            'estado' => Programa::ESTADO_PUBLICADO,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListProgramas::class)
            ->callTableAction('publicar', $programa);
        $this->assertSame(Programa::ESTADO_BORRADOR, $programa->fresh()->estado);

        Livewire::test(ListProgramas::class)
            ->callTableAction('publicar', $programa);
        $this->assertSame(Programa::ESTADO_PUBLICADO, $programa->fresh()->estado);
    }

    /** Obs. N.º 5 — el índice se refresca al cambiar el contenido, sin esperar al TTL. */
    public function test_el_indice_del_buscador_se_actualiza_al_editar_contenido(): void
    {
        $this->getJson('/api/v1/buscador')
            ->assertOk()
            ->assertJsonMissing(['titulo' => 'Diplomado en Paleografía']);

        Programa::create([
            'grado' => 'Taller', 'nombre' => 'Diplomado en Paleografía',
            'modalidad' => 'Virtual', 'duracion' => 2, 'creditos' => 12,
            'estado' => Programa::ESTADO_PUBLICADO,
        ]);

        // Sin esperar al TTL: guardar invalida el índice. Importa más que
        // antes, porque publicar también dispara la reconstrucción del sitio
        // y el índice se congela en ese build.
        $this->getJson('/api/v1/buscador')
            ->assertOk()
            ->assertJsonFragment(['titulo' => 'Diplomado en Paleografía']);
    }
}
