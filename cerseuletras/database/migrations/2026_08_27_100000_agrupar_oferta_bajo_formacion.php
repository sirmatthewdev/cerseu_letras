<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa Talleres, Cursos y Especializaciones bajo un desplegable «Formación».
 *
 * El seeder ya crea el menú con esta forma, pero solo siembra cuando la tabla
 * está vacía: las instalaciones que ya existen se quedarían con los tres tipos
 * sueltos en primer nivel. De ahí esta migración.
 *
 * El menú es editable desde el panel, así que esto **no** reorganiza a ciegas:
 * solo actúa si los tres siguen siendo entradas de primer nivel y no existe ya
 * un «Formación». Si alguien los movió, los renombró o ya hizo esta agrupación a
 * mano, la migración se queda quieta antes que deshacer su trabajo.
 */
return new class extends Migration
{
    /** Las rutas identifican a los tres; la etiqueta puede haberse editado. */
    private const RUTAS = ['talleres.index', 'cursos.index', 'especializaciones.index'];

    public function up(): void
    {
        $sueltos = DB::table('menu_items')
            ->whereNull('parent_id')
            ->whereIn('route_name', self::RUTAS)
            ->orderBy('orden')
            ->get();

        // Ni uno solo en primer nivel: o ya están agrupados, o alguien rehizo el
        // menú. En cualquiera de los dos casos, no hay nada que arreglar.
        if ($sueltos->isEmpty()) {
            return;
        }

        $yaHayPadre = DB::table('menu_items')
            ->whereNull('parent_id')
            ->where('etiqueta', 'Formación')
            ->exists();

        if ($yaHayPadre) {
            return;
        }

        // El desplegable ocupa el sitio del primero de los tres, para no alterar
        // el orden del resto de la cabecera.
        $orden = (int) $sueltos->first()->orden;

        $padre = DB::table('menu_items')->insertGetId([
            'parent_id' => null,
            'etiqueta' => 'Formación',
            'route_name' => null,
            'url' => null,
            'icono' => 'fas-graduation-cap',
            'nueva_pestana' => false,
            'orden' => $orden,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Se recolocan en el orden en que estaban, que es el de duración
        // creciente: talleres, cursos, especializaciones.
        foreach ($sueltos->values() as $posicion => $item) {
            DB::table('menu_items')
                ->where('id', $item->id)
                ->update([
                    'parent_id' => $padre,
                    'orden' => $posicion,
                    'updated_at' => now(),
                ]);
        }

        // Los que quedan por encima del hueco no se tocan; los de debajo suben
        // las dos posiciones que dejan libres los dos tipos absorbidos.
        DB::table('menu_items')
            ->whereNull('parent_id')
            ->where('id', '!=', $padre)
            ->where('orden', '>', $orden)
            ->decrement('orden', max(0, $sueltos->count() - 1));

        $this->olvidarCache();
    }

    public function down(): void
    {
        $padre = DB::table('menu_items')
            ->whereNull('parent_id')
            ->where('etiqueta', 'Formación')
            ->first();

        if (! $padre) {
            return;
        }

        $hijos = DB::table('menu_items')
            ->where('parent_id', $padre->id)
            ->whereIn('route_name', self::RUTAS)
            ->orderBy('orden')
            ->get();

        // Se devuelven a primer nivel a partir del sitio que ocupaba el padre,
        // empujando hacia abajo lo que venía después.
        DB::table('menu_items')
            ->whereNull('parent_id')
            ->where('orden', '>', $padre->orden)
            ->increment('orden', max(0, $hijos->count() - 1));

        foreach ($hijos->values() as $posicion => $hijo) {
            DB::table('menu_items')
                ->where('id', $hijo->id)
                ->update([
                    'parent_id' => null,
                    'orden' => $padre->orden + $posicion,
                    'updated_at' => now(),
                ]);
        }

        // Solo se borra si quedó vacío: si el equipo colgó algo más de
        // «Formación», eso se perdería al borrarlo en cascada.
        $vacio = ! DB::table('menu_items')->where('parent_id', $padre->id)->exists();

        if ($vacio) {
            DB::table('menu_items')->where('id', $padre->id)->delete();
        }

        $this->olvidarCache();
    }

    /**
     * El menú se sirve cacheado; sin esto la cabecera seguiría mostrando la
     * forma anterior hasta que algo más invalidara la caché.
     */
    private function olvidarCache(): void
    {
        if (method_exists(\App\Models\MenuItem::class, 'clearCache')) {
            \App\Models\MenuItem::clearCache();
        }
    }
};
