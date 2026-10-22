<?php

namespace Tests\Feature;

use App\Filament\Resources\Programas\Pages\EditPrograma;
use App\Filament\Resources\Programas\Pages\ListProgramas;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La oferta se enciende y se apaga como la plana docente: visible o no.
 *
 * Hay cursos que van por temporadas, y lo que se hace a diario es ocultarlos y
 * volver a mostrarlos. Por dentro sigue siendo `estado` —publicado o
 * borrador—, que es lo que leen la API y el sitio.
 */
class ProgramasVisiblesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function programa(string $estado, string $nombre = 'Taller de cine'): Programa
    {
        return Programa::create([
            'grado' => 'Taller',
            'nombre' => $nombre,
            'slug' => \Illuminate\Support\Str::slug($nombre),
            'estado' => $estado,
        ]);
    }

    public function test_el_interruptor_de_la_tabla_oculta_y_vuelve_a_mostrar(): void
    {
        $programa = $this->programa(Programa::ESTADO_PUBLICADO);

        $tabla = Livewire::test(ListProgramas::class);

        $tabla->call('updateTableColumnState', 'estado', (string) $programa->getKey(), false);
        $this->assertSame(Programa::ESTADO_BORRADOR, $programa->fresh()->estado);

        $tabla->call('updateTableColumnState', 'estado', (string) $programa->getKey(), true);
        $this->assertSame(Programa::ESTADO_PUBLICADO, $programa->fresh()->estado);
    }

    public function test_oculto_no_sale_en_la_api_y_visible_si(): void
    {
        $visible = $this->programa(Programa::ESTADO_PUBLICADO, 'Curso de verano');
        $oculto = $this->programa(Programa::ESTADO_BORRADOR, 'Curso de invierno');

        $this->getJson('/api/v1/programas/' . $visible->slug)->assertOk();
        $this->getJson('/api/v1/programas/' . $oculto->slug)->assertNotFound();
    }

    public function test_la_ficha_guarda_el_interruptor_como_estado(): void
    {
        $programa = $this->programa(Programa::ESTADO_BORRADOR);

        Livewire::test(EditPrograma::class, ['record' => $programa->getRouteKey()])
            ->assertSchemaStateSet(['estado' => false])
            ->fillForm(['estado' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Programa::ESTADO_PUBLICADO, $programa->fresh()->estado);
    }

    public function test_se_ocultan_y_muestran_varios_a_la_vez(): void
    {
        $uno = $this->programa(Programa::ESTADO_PUBLICADO, 'Uno');
        $dos = $this->programa(Programa::ESTADO_PUBLICADO, 'Dos');
        $otro = $this->programa(Programa::ESTADO_PUBLICADO, 'Se queda');

        Livewire::test(ListProgramas::class)->callTableBulkAction('ocultar', [$uno, $dos]);

        $this->assertSame(Programa::ESTADO_BORRADOR, $uno->fresh()->estado);
        $this->assertSame(Programa::ESTADO_BORRADOR, $dos->fresh()->estado);
        $this->assertSame(Programa::ESTADO_PUBLICADO, $otro->fresh()->estado);

        Livewire::test(ListProgramas::class)->callTableBulkAction('hacer_visibles', [$uno, $dos]);

        $this->assertSame(Programa::ESTADO_PUBLICADO, $uno->fresh()->estado);
        $this->assertSame(Programa::ESTADO_PUBLICADO, $dos->fresh()->estado);
    }

    public function test_la_fecha_limite_es_texto_libre_y_no_rompe_la_api(): void
    {
        // Lo que se escribio en el Curso de Ballet Clasico y dejo la API en 500.
        $programa = $this->programa(Programa::ESTADO_PUBLICADO);
        $programa->update(['fecha_limite_inscripcion' => 'Lunes 02 de noviembre']);

        $this->getJson('/api/v1/programas?tipo=talleres')->assertOk();
        $this->getJson('/api/v1/programas/' . $programa->slug)
            ->assertOk()
            ->assertJsonPath('data.fecha_limite', 'Lunes 02 de noviembre');
    }

    public function test_la_ficha_abre_con_el_interruptor_como_esta_el_programa(): void
    {
        // Abrir un programa visible lo mostraba como «no visible», y guardar
        // sin tocar nada lo ocultaba del sitio.
        $visible = $this->programa(Programa::ESTADO_PUBLICADO, 'Visible');
        $oculto = $this->programa(Programa::ESTADO_BORRADOR, 'Oculto');

        Livewire::test(EditPrograma::class, ['record' => $visible->getRouteKey()])
            ->assertSchemaStateSet(['estado' => true])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(Programa::ESTADO_PUBLICADO, $visible->fresh()->estado);

        Livewire::test(EditPrograma::class, ['record' => $oculto->getRouteKey()])
            ->assertSchemaStateSet(['estado' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(Programa::ESTADO_BORRADOR, $oculto->fresh()->estado);
    }
}
