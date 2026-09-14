<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una línea más en el hero de la portada: el mensaje institucional.
 *
 * El hero tenía tres textos —antetítulo, titular y párrafo— y el rediseño pide
 * cuatro: entre el nombre y la descripción va una frase corta que dice a qué se
 * dedica el CERSEU. No es un eslogan de campaña, es la línea que hoy falta para
 * que el titular no salte directamente al párrafo largo.
 *
 * Se llama `claim` porque es el mismo papel que ya cumple `claim` en los heros
 * de talleres, cursos y especializaciones —«Con docentes de la UNMSM, el
 * conocimiento humanístico al alcance de todos»—, y llamarlo de otra forma
 * habría dejado dos nombres para una misma cosa.
 *
 * Va al panel, no a la plantilla del sitio: cambiar esa frase no debe exigir un
 * despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('home_hero_claim')->nullable()->after('home_hero_titulo');
        });

        /*
         * El texto con el que arranca. La Unidad lo cambia desde Configuración
         * cuando quiera; esto es solo para que el hero no salga con el hueco
         * vacío en la primera carga.
         */
        DB::table('site_settings')
            ->whereNull('home_hero_claim')
            ->update(['home_hero_claim' => 'La Facultad de Letras al servicio de la comunidad.']);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('home_hero_claim');
        });
    }
};
