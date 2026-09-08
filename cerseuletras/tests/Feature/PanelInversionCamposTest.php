<?php

namespace Tests\Feature;

use App\Filament\Resources\Programas\Schemas\ProgramaForm;
use App\Models\User;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los campos de inversión están en el formulario del programa.
 *
 * Comprobaba los `id` del HTML del formulario Blade (`inv_costo_total`…), que
 * ya no existe. Se reapunta al esquema de Filament en vez de borrarse: lo que
 * guardaba sigue importando —que ningún bloque de la inversión desaparezca del
 * formulario sin que nadie lo note— y son los campos que pidió la Unidad de
 * Posgrado en sus observaciones.
 *
 * Se pregunta al esquema y no al HTML: el marcado de Filament es suyo y puede
 * cambiar entre versiones, pero los nombres de los campos son nuestros.
 */
class PanelInversionCamposTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function camposDelFormulario(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        $esquema = ProgramaForm::configure(Schema::make(
            \Livewire\Livewire::test(\App\Filament\Resources\Programas\Pages\CreatePrograma::class)->instance()
        ));

        $nombres = [];

        $recorrer = function ($componentes) use (&$recorrer, &$nombres): void {
            foreach ($componentes as $componente) {
                if ($componente instanceof \Filament\Forms\Components\Field) {
                    $nombres[] = $componente->getName();
                }

                if (method_exists($componente, 'getDefaultChildComponents')) {
                    $recorrer($componente->getDefaultChildComponents());
                }
            }
        };

        $recorrer($esquema->getComponents());

        return array_values(array_unique($nombres));
    }

    public function test_el_formulario_expone_los_campos_de_inversion(): void
    {
        $campos = $this->camposDelFormulario();

        foreach ([
            'inversion_economica.derecho_inscripcion.bachiller_unmsm',
            'inversion_economica.derecho_inscripcion.otras_universidades',
            'inversion_economica.costo_total',
            'inversion_economica.costo_diploma',
            'inversion_economica.costo_matricula',
        ] as $campo) {
            $this->assertContains($campo, $campos, "Falta el campo {$campo}");
        }
    }

    public function test_las_modalidades_y_las_condiciones_siguen_siendo_listas(): void
    {
        $campos = $this->camposDelFormulario();

        // Los dos son repetidores: la Unidad añade y quita filas, no escribe
        // JSON a mano como en el formulario anterior.
        $this->assertContains('inversion_economica.modalidades', $campos);
        $this->assertContains('inversion_economica.condiciones', $campos);
    }
}
