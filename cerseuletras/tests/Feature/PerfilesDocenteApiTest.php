<?php

namespace Tests\Feature;

use App\Models\Docente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los perfiles academicos de un docente en la API.
 *
 * El listado solo devolvia nombre, grado, foto y slug, asi que la tarjeta de la
 * plana docente no tenia con que pintar los iconos de ORCID, CTI Vitae y
 * LinkedIn: habria hecho falta pedir las 20 fichas para dibujar una rejilla.
 *
 * Y una linea que no se cruza: el correo del docente NO se expone, ni en el
 * listado ni en la ficha. Publicarlo en HTML estatico es entregarselo a
 * cualquier rastreador, y de ahi no se vuelve.
 */
class PerfilesDocenteApiTest extends TestCase
{
    use RefreshDatabase;

    private function docente(array $atributos = []): Docente
    {
        return Docente::create(array_merge([
            'nombres' => 'Ada',
            'apellidos' => 'Lovelace',
            'estado' => 1,
            'email' => 'ada@example.test',
        ], $atributos));
    }

    public function test_el_listado_incluye_los_perfiles(): void
    {
        $this->docente([
            'orcid' => '0000-0003-1753-7448',
            'cti_vitae' => 'https://ctivitae.concytec.gob.pe/x',
            'linkedin' => 'linkedin.com/in/ada',
        ]);

        $fila = $this->getJson('/api/v1/docentes')->assertOk()->json('data.0');

        $this->assertSame('0000-0003-1753-7448', $fila['orcid']);
        $this->assertSame('https://ctivitae.concytec.gob.pe/x', $fila['cti_vitae']);
        $this->assertSame('linkedin.com/in/ada', $fila['linkedin']);
    }

    /**
     * Se entregan tal como estan guardados. Completarlos es cosa del sitio
     * —`perfilesDe`, en TypeScript—: la API no decide como se enlaza.
     */
    public function test_los_perfiles_vacios_llegan_como_null(): void
    {
        $this->docente();

        $fila = $this->getJson('/api/v1/docentes')->assertOk()->json('data.0');

        $this->assertNull($fila['orcid']);
        $this->assertNull($fila['cti_vitae']);
        $this->assertNull($fila['linkedin']);
    }

    public function test_el_correo_no_se_publica(): void
    {
        $docente = $this->docente();

        $listado = $this->getJson('/api/v1/docentes')->assertOk();
        $listado->assertDontSee('ada@example.test');

        $ficha = $this->getJson('/api/v1/docentes/' . $docente->slug)->assertOk();
        $ficha->assertDontSee('ada@example.test');
    }
}
