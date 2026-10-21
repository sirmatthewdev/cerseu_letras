<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fuera las tarifas por crédito de Posgrado.
 *
 * `costo_por_credito` y `semestres_inversion` servían para calcular el costo
 * de una maestría a partir de sus créditos por semestre. El CERSEU publica
 * precios cerrados —general y comunidad sanmarquina—, el panel ya no las edita
 * desde que «Costo por crédito» pasó a ser «Costo por módulo», y ningún
 * programa las tenía cargadas al retirarlas.
 *
 * Si alguna instalación sí tuviera importes, la migración se detiene en vez de
 * borrarlos: hay que pasarlos antes a la inversión del programa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conTarifas = DB::table('programas')
            ->where(fn ($q) => $q
                ->whereNotNull('costo_por_credito')
                ->orWhere(fn ($q) => $q
                    ->whereNotNull('semestres_inversion')
                    ->whereNotIn('semestres_inversion', ['[]', 'null'])))
            ->pluck('nombre');

        if ($conTarifas->isNotEmpty()) {
            throw new RuntimeException(
                'Hay programas con tarifas por crédito cargadas: ' . $conTarifas->implode(', ')
                . '. Pasa sus importes a la inversión del programa antes de migrar.'
            );
        }

        Schema::table('programas', function (Blueprint $table) {
            $table->dropColumn(['costo_por_credito', 'semestres_inversion']);
        });
    }

    /** Vuelven las columnas, vacías: no había datos que restaurar. */
    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->unsignedInteger('costo_por_credito')->nullable()->after('inversion_economica');
            $table->json('semestres_inversion')->nullable()->after('costo_por_credito');
        });
    }
};
