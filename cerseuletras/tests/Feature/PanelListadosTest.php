<?php

namespace Tests\Feature;

use App\Filament\Resources\Docentes\Pages\ListDocentes;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Models\Docente;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Listados del panel: paginación y validación de subidas.
 *
 * Entraba por `/admin/docentes` y `/admin/documents`. Se reapunta a Filament en
 * lugar de borrarse: que un listado no se traiga la tabla entera, y que no se
 * cuele un ejecutable disfrazado de documento, son ciertas con cualquier panel.
 */
class PanelListadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    /** No hay factory para Docente; se crean a mano. */
    private function docentes(int $cuantos): void
    {
        foreach (range(1, $cuantos) as $i) {
            Docente::create([
                'nombres' => 'Ana',
                'apellidos' => 'Apellido ' . $i,
                'email' => "docente{$i}@ejemplo.pe",
                'estado' => 'activo',
            ]);
        }
    }

    /**
     * Con la plana docente entera en una sola pantalla, el panel tarda más
     * cuanto más crece el sitio, hasta que un día deja de abrir.
     *
     * No se fija el tamaño de página a mano: es cosa de Filament y puede
     * cambiar. Lo que se guarda es que haya paginación, no cuál.
     */
    public function test_el_listado_de_docentes_pagina_en_vez_de_traerlo_todo(): void
    {
        $this->docentes(30);

        $mostrados = Livewire::test(ListDocentes::class)
            ->instance()
            ->getTableRecords()
            ->count();

        $this->assertLessThan(
            Docente::count(),
            $mostrados,
            'El listado se trae todas las fichas de golpe.'
        );
    }

    public function test_documentos_rechaza_un_archivo_de_tipo_no_permitido(): void
    {
        Storage::fake('public');

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'type' => 'reglamento',
                'title' => 'Ejecutable disfrazado',
                'url' => [UploadedFile::fake()->create('malicioso.php', 20, 'application/x-php')],
            ])
            ->call('create')
            ->assertHasFormErrors(['url']);

        $this->assertSame(0, Document::count());
    }

    public function test_documentos_acepta_un_pdf(): void
    {
        Storage::fake('public');

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'type' => 'reglamento',
                'title' => 'Reglamento vigente',
                'url' => [UploadedFile::fake()->create('reglamento.pdf', 40, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Document::count());
    }
}
