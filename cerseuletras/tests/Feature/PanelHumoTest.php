<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Prueba de humo del panel: abre TODAS sus pantallas con un admin autenticado y
 * comprueba que ninguna revienta.
 *
 * Recorría el panel Blade, que ya no existe. Se reapunta al de Filament en vez
 * de borrarse, porque lo que detecta no lo ve ninguna otra: un campo mal
 * declarado, un enum donde se espera una cadena o una relación que no existe no
 * fallan al arrancar la aplicación, sino al pintar esa pantalla concreta.
 *
 * **La edición es la parte que importa.** Ya había una prueba que abría listado
 * y creación de cada recurso, y aun así la pantalla de edición de la admisión
 * llevaba tiempo reventando con un TypeError: su título salía de un atributo
 * casteado a enum y Filament exige una cadena. Listado y creación no lo tocaban
 * —no hay registro que titular— así que nada lo veía. De ahí que aquí se abra
 * con un registro de verdad.
 *
 * Los recursos se enumeran preguntándole al panel, no a mano: uno nuevo entra
 * en el recorrido sin que nadie se acuerde de añadirlo.
 */
class PanelHumoTest extends TestCase
{
    use RefreshDatabase;

    public function test_todas_las_pantallas_del_panel_responden(): void
    {
        Artisan::call('db:seed');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $revisadas = 0;
        $fallos = [];

        foreach (Filament::getPanel('admin')->getResources() as $recurso) {
            $paginas = $recurso::getPages();
            $modelo = $recurso::getModel();

            foreach (['index', 'create'] as $pagina) {
                if (! isset($paginas[$pagina])) {
                    continue;
                }

                $revisadas++;
                $fallos = array_merge($fallos, $this->revisar($recurso::getUrl($pagina)));
            }

            if (! isset($paginas['edit'])) {
                continue;
            }

            // Con un registro real, no con un id inventado: un 404 legítimo no
            // prueba nada. Si la tabla está vacía tras el seeder, se omite.
            $registro = $modelo::query()->first();

            if (! $registro instanceof Model) {
                continue;
            }

            $revisadas++;
            $fallos = array_merge($fallos, $this->revisar($recurso::getUrl('edit', ['record' => $registro])));
        }

        // Las páginas propias no son recursos y no salen del recorrido anterior.
        foreach (Filament::getPanel('admin')->getPages() as $pagina) {
            $revisadas++;
            $fallos = array_merge($fallos, $this->revisar($pagina::getUrl()));
        }

        $this->assertGreaterThan(20, $revisadas, 'Se recorrieron muy pocas pantallas; la deteccion de recursos falla.');
        $this->assertSame([], $fallos, "Pantallas del panel que no responden:\n" . implode("\n", $fallos));
    }

    /** @return list<string> */
    private function revisar(string $url): array
    {
        $respuesta = $this->get($url);

        if ($respuesta->status() < 400) {
            return [];
        }

        return [
            $url . ' → ' . $respuesta->status() . ' :: '
                . ($respuesta->exception?->getMessage() ?? '(sin excepcion)'),
        ];
    }

    public function test_la_exportacion_de_solicitudes_devuelve_un_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $respuesta = $this->actingAs($admin)->get('/gestion/solicitudes/exportar');

        $respuesta->assertOk();
        $this->assertStringContainsString('csv', strtolower((string) $respuesta->headers->get('content-type')));
    }
}
