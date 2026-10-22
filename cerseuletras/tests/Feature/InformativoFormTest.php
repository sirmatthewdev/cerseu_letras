<?php

namespace Tests\Feature;

use App\Filament\Resources\Informativos\Pages\CreateInformativo;
use App\Models\Informativo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La categoría es obligatoria en la base, así que también en el formulario.
 *
 * Antes el panel la dejaba vacía y el fallo llegaba al guardar, como error de
 * la base: el registro no se creaba y el PDF ya subido quedaba suelto.
 */
class InformativoFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_categoria_lo_dice_el_formulario_y_no_la_base(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(CreateInformativo::class)
            ->fillForm(['titulo' => 'TEMARIO', 'categoria' => null, 'tipo' => 1, 'url' => 'https://ejemplo.pe/temario.pdf'])
            ->call('create')
            ->assertHasFormErrors(['categoria' => 'required']);

        $this->assertSame(0, Informativo::count());
    }

    public function test_con_categoria_se_guarda(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(CreateInformativo::class)
            ->fillForm(['titulo' => 'TEMARIO', 'categoria' => 'Cursos', 'tipo' => 1, 'url' => 'https://ejemplo.pe/temario.pdf'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Cursos', Informativo::first()->categoria);
    }
}
