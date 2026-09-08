<?php

namespace Tests\Feature;

use App\Filament\Componentes\ImagenOptimizada;
use App\Filament\Resources\Docentes\Pages\CreateDocente;
use App\Models\Docente;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Subir imágenes desde el panel.
 *
 * Esto existe por un fallo que estuvo activo y no vio nadie: **ninguna imagen
 * se podía subir por el panel**. El cierre que guarda el fichero nombraba su
 * parámetro `$archivo`, y Filament lo invoca con
 * `evaluate($callback, ['file' => $file])`, que empareja por NOMBRE. Al no
 * casar, Filament caía a su respaldo —resolverlo por tipo desde el contenedor—
 * y el contenedor no sabe construir un fichero subido: cada intento moría con
 * `BindingResolutionException`.
 *
 * No lo detectó nada porque las pruebas que había abrían el formulario y
 * comprobaban los campos, pero ninguna llegaba a guardar con un fichero de
 * verdad. El formulario se veía perfecto; el fallo estaba en el botón de
 * guardar.
 *
 * De ahí que aquí se suba de verdad, y no en un recurso sino en todos los que
 * tengan campo de imagen: el fallo ya estaba duplicado en dos sitios —el
 * componente compartido y una copia a mano en la ficha del docente— y una
 * prueba por recurso habría dejado pasar la copia.
 */
class PanelSubidaDeImagenesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    public function test_la_foto_del_docente_se_sube_y_se_optimiza(): void
    {
        Storage::fake('public');

        Livewire::test(CreateDocente::class)
            ->fillForm([
                'nombres' => 'Ana',
                'apellidos' => 'Quispe',
                'foto' => [UploadedFile::fake()->image('retrato.jpg', 1600, 1600)],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Por nombre y no `first()`: las migraciones cargan plana docente
        // oficial, asi que la primera fila no es la que acaba de crearse.
        $docente = Docente::where('nombres', 'Ana')->firstOrFail();

        Storage::disk('public')->assertExists($docente->foto);
        $this->assertStringEndsWith('.webp', $docente->foto);

        // 800 px es el ancho que declara el propio campo. Sin el optimizador se
        // guardarian los 1600 originales.
        [$ancho] = \App\Support\OptimizadorImagen::medidas($docente->foto);
        $this->assertSame(800, $ancho);
    }

    /**
     * El guardián de verdad: que ningún campo de imagen del panel vuelva a
     * nombrar mal el parámetro de su cierre.
     *
     * Se comprueba sobre el componente compartido, que es por donde deben pasar
     * todos. Si alguien vuelve a copiarlo a mano y se equivoca en el nombre, lo
     * que falla es la prueba de arriba en su recurso; esta cierra la puerta a
     * que el fallo vuelva por el sitio original.
     */
    public function test_el_cierre_de_guardado_nombra_su_parametro_como_espera_filament(): void
    {
        $campo = ImagenOptimizada::make('imagen', 'anuncios', 1200);

        $this->assertInstanceOf(FileUpload::class, $campo);

        $cierre = (function () {
            return $this->saveUploadedFileUsing;
        })->call($campo);

        $parametros = (new \ReflectionFunction($cierre))->getParameters();

        $nombres = array_map(fn (\ReflectionParameter $p): string => $p->getName(), $parametros);

        $this->assertContains(
            'file',
            $nombres,
            'Filament pasa el fichero como «file»: con otro nombre no lo empareja y la subida revienta.'
        );
    }
}
