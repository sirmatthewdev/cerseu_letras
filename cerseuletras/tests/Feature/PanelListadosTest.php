<?php

namespace Tests\Feature;

use App\Models\Docente;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Listados del panel: paginación y validación de subidas.
 */
class PanelListadosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    /** No hay factory para Docente; se crean a mano. */
    private function docentes(int $cuantos, string $nombres = 'Ana'): void
    {
        foreach (range(1, $cuantos) as $i) {
            Docente::create([
                'nombres' => $nombres,
                'apellidos' => 'Apellido ' . $i,
                'email' => "docente{$i}@ejemplo.pe",
                'estado' => 'activo',
            ]);
        }
    }

    public function test_el_listado_de_docentes_pagina_en_vez_de_traerlo_todo(): void
    {
        // El total no se fija a mano: las migraciones cargan plana docente
        // oficial, y cada expositor que incorpora la Unidad rompia la prueba
        // sin que la paginacion —que es lo que aqui se vigila— hubiera cambiado.
        $antes = Docente::count();
        $this->docentes(30);

        $respuesta = $this->actingAs($this->admin())->get('/admin/docentes')->assertOk();

        $docentes = $respuesta->viewData('docentes');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $docentes);
        $this->assertCount(25, $docentes->items());
        $this->assertSame($antes + 30, $docentes->total());
    }

    public function test_la_busqueda_sobrevive_al_cambio_de_pagina(): void
    {
        // Sin `withQueryString()` el filtro se perdía al pasar de página.
        $this->docentes(30, 'Ana');

        $respuesta = $this->actingAs($this->admin())
            ->get('/admin/docentes?search=Ana&page=2')
            ->assertOk();

        $this->assertStringContainsString('search=Ana', (string) $respuesta->viewData('docentes')->nextPageUrl()
            ?: $respuesta->viewData('docentes')->previousPageUrl());
    }

    public function test_documentos_rechaza_un_archivo_de_tipo_no_permitido(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/documents', [
                'type' => 'reglamento',
                'title' => 'Ejecutable disfrazado',
                'file' => UploadedFile::fake()->create('malicioso.php', 20, 'application/x-php'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_documentos_acepta_un_pdf(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/documents', [
                'type' => 'reglamento',
                'title' => 'Reglamento vigente',
                'file' => UploadedFile::fake()->create('reglamento.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Document::count());
    }
}
