<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\TipoOferta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * El hero de cada tipo de oferta se puede editar.
 *
 * Al añadir «Especialización» como tercer tipo se crearon sus cuatro columnas
 * de hero, pero no se añadieron a `$fillable`. El efecto es de los peores que
 * hay: `fill()` las descarta **en silencio**, así que el panel decía «guardado»
 * y esos cuatro campos seguían vacíos. Ninguna pantalla fallaba.
 *
 * Esta prueba recorre los tipos desde el enum, no una lista escrita a mano: el
 * cuarto tipo que se añada queda cubierto el día que se añada, sin que nadie
 * tenga que acordarse de volver aquí.
 */
class HeroDeCadaTipoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_tipo_tiene_columnas_de_hero(): void
    {
        foreach (TipoOferta::cases() as $tipo) {
            foreach (['titulo', 'texto', 'claim', 'imagen'] as $campo) {
                $columna = $tipo->slug() . '_hero_' . $campo;

                $this->assertTrue(
                    Schema::hasColumn('site_settings', $columna),
                    "Falta la columna {$columna} para el tipo «{$tipo->plural()}»."
                );
            }
        }
    }

    public function test_el_hero_de_cada_tipo_se_puede_guardar(): void
    {
        $ajustes = SiteSetting::query()->firstOrCreate([]);

        foreach (TipoOferta::cases() as $tipo) {
            $columna = $tipo->slug() . '_hero_titulo';
            $valor = 'Hero de ' . $tipo->plural();

            // `fill()` y no asignación directa: es lo que hace un formulario, y
            // es justo donde la máscara de `$fillable` decide en silencio.
            $ajustes->fill([$columna => $valor])->save();

            $this->assertSame(
                $valor,
                $ajustes->fresh()->{$columna},
                "«{$tipo->plural()}» no guarda su hero: revisa \$fillable en SiteSetting."
            );
        }
    }

    /** Y que llegue al sitio, que es para lo que se edita. */
    public function test_el_hero_guardado_llega_a_la_api(): void
    {
        $ajustes = SiteSetting::query()->firstOrCreate([]);

        foreach (TipoOferta::cases() as $tipo) {
            $ajustes->fill([$tipo->slug() . '_hero_titulo' => 'Titular de ' . $tipo->slug()])->save();
        }

        $tipos = $this->getJson('/api/v1/tipos-oferta')->assertOk()->json('data');

        foreach ($tipos as $tipo) {
            $this->assertSame(
                'Titular de ' . $tipo['slug'],
                $tipo['hero']['titulo'],
                "El hero de «{$tipo['plural']}» no llega a la API."
            );
        }
    }
}
