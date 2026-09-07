<?php

namespace Tests\Feature;

use App\Filament\Pages\Papelera;
use App\Models\Docente;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La papelera del panel.
 *
 * Lo que hay que comprobar de una papelera no es que pinte una lista: es que lo
 * borrado siga ahí y se pueda devolver. Una papelera que enseña bien y no
 * restaura es peor que no tenerla, porque nadie descubre que no funciona hasta
 * el día que la necesita.
 */
class PapeleraTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function programa(string $nombre = 'Curso borrado'): Programa
    {
        return Programa::create([
            'grado' => 'Curso',
            'nombre' => $nombre,
            'slug' => \Illuminate\Support\Str::slug($nombre),
            'estado' => Programa::ESTADO_PUBLICADO,
        ]);
    }

    public function test_recoge_lo_borrado_de_varios_modelos(): void
    {
        $this->programa('Curso que se borró')->delete();

        Docente::create(['nombres' => 'Ada', 'apellidos' => 'Lovelace', 'estado' => 1])->delete();

        $this->actingAs($this->admin());

        Livewire::test(Papelera::class)
            ->assertSee('Curso que se borró')
            ->assertSee('Ada Lovelace')
            ->assertSee('Programa')
            ->assertSee('Docente');
    }

    public function test_lo_que_no_esta_borrado_no_sale(): void
    {
        $this->programa('Curso vivo');

        $this->actingAs($this->admin());

        Livewire::test(Papelera::class)->assertDontSee('Curso vivo');
    }

    /** Lo que de verdad importa: que devuelva el registro al sitio. */
    public function test_restaura_y_el_registro_vuelve(): void
    {
        $programa = $this->programa('Curso recuperable');
        $programa->delete();

        $this->assertSoftDeleted($programa);

        $this->actingAs($this->admin());

        Livewire::test(Papelera::class)
            // `TestAction::table($clave)` y no `callAction(..., record:)`:
            // en una accion de fila la clave va por ahi, y el parametro con
            // nombre no existe en esta version.
            ->callAction(TestAction::make('restaurar')->table('programas-' . $programa->id));

        $this->assertNotSoftDeleted($programa);

        // Y que vuelva a estar publicado de verdad, no solo sin marca de
        // borrado: es lo que espera quien pulsa «restaurar».
        $this->getJson('/api/v1/programas/curso-recuperable')->assertOk();
    }

    /**
     * Dos modelos distintos pueden tener el mismo id. Si la clave de la tabla
     * fuera solo el id, uno taparía al otro y restaurar devolvería el que no
     * era — el fallo más silencioso que puede tener una papelera.
     */
    public function test_dos_registros_con_el_mismo_id_no_se_tapan(): void
    {
        $programa = $this->programa('Programa numero uno');
        $docente = Docente::create(['nombres' => 'Grace', 'apellidos' => 'Hopper', 'estado' => 1]);

        $programa->delete();
        $docente->delete();

        $this->actingAs($this->admin());

        Livewire::test(Papelera::class)
            ->assertSee('Programa numero uno')
            ->assertSee('Grace Hopper');

        // Se restaura solo el docente; el programa tiene que seguir borrado.
        Livewire::test(Papelera::class)
            ->callAction(TestAction::make('restaurar')->table('docentes-' . $docente->id));

        $this->assertNotSoftDeleted($docente);
        $this->assertSoftDeleted($programa);
    }

    public function test_la_papelera_exige_ser_administrador(): void
    {
        $this->get('/panel/papelera')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get('/panel/papelera')
            ->assertForbidden();
    }
}
