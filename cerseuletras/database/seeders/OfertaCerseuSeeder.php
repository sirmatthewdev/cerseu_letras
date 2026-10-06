<?php

namespace Database\Seeders;

use App\Models\AdmisionCronogramaItem;
use App\Models\AdmisionSetting;
use App\Models\Docente;
use App\Models\Programa;
use App\Models\TipoOferta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Oferta real del CERSEU: la programación 2026 que envió la unidad.
 *
 * El cuadro de origen tiene 47 dictados de 39 cursos distintos —«Redacción de
 * Tesis I» se repite cuatro veces a lo largo del año, entre otros—, así que se
 * separa en dos cosas: la ficha del curso, que es una por nombre, y sus
 * convocatorias, que van al cronograma de admisión del módulo de Cursos.
 *
 * Los datos viven en data/oferta-cerseu-2026.json para poder regenerarlos
 * desde la hoja de cálculo sin tocar este archivo. **Siete de las sumillas de
 * ese fichero son el texto oficial de la Unidad** y no la línea generada a
 * partir de las horas y la modalidad: si se regenera desde la hoja, hay que
 * volver a traerlas de las migraciones `2026_09_07_100000` y `110000`, que son
 * de donde salieron.
 */
class OfertaCerseuSeeder extends Seeder
{
    public function run(): void
    {
        $datos = json_decode(
            file_get_contents(database_path('seeders/data/oferta-cerseu-2026.json')),
            true
        );

        /*
         * La guarda pregunta si YA ESTA CARGADA ESTA OFERTA, no si existe algun
         * curso.
         *
         * Preguntaba lo segundo —`deTipo(Curso)->exists()`— y en una instalacion
         * limpia eso se cumplia sin que nadie hubiera sembrado nada: la
         * migracion de las sumillas oficiales de 2026 crea cinco cursos en
         * borrador, y las migraciones corren ANTES que los seeders. El seeder
         * avisaba «ya hay cursos cargados», se iba, y la instalacion quedaba con
         * 5 cursos en borrador en lugar de los 39 publicados: el sitio se
         * construia con 17 paginas en vez de 80, sin un solo error en ningun
         * registro. Se encontro desplegando en la VM, no aqui.
         *
         * Comparar contra los nombres del propio fichero de datos acierta en los
         * dos casos que importan: no duplica la oferta si alguien vuelve a
         * sembrar, y no se cree cargada por cursos que vinieron de otro sitio
         * —de esa migracion, o creados a mano desde el panel.
         *
         * Si la oferta esta a medias porque alguien borro parte, esto no la
         * completa: se detiene igual. Rellenar huecos a ciegas sobre contenido
         * que la Unidad pudo editar es peor que no tocar nada.
         */
        $nombres = array_column($datos['cursos'], 'nombre');

        if (Programa::deTipo(TipoOferta::Curso)->whereIn('nombre', $nombres)->exists()) {
            $this->command?->warn('La oferta del CERSEU ya está cargada; no se toca.');

            return;
        }

        $docentes = $this->docentes($datos['docentes']);
        $cursos = $this->cursos($datos['cursos'], $docentes);
        $this->cronograma($datos['cronograma'], $cursos);

        $this->command?->info(sprintf(
            'CERSEU 2026: %d cursos, %d docentes, %d convocatorias.',
            count($cursos),
            count($docentes),
            count($datos['cronograma'])
        ));
    }

    /** @return array<string, Docente> indexados por «Nombres Apellidos» */
    private function docentes(array $filas): array
    {
        $docentes = [];

        foreach ($filas as $fila) {
            $completo = trim($fila['nombres'] . ' ' . $fila['apellidos']);

            $docentes[$completo] = Docente::firstOrCreate(
                ['slug' => Str::slug($completo)],
                [
                    'nombres' => $fila['nombres'],
                    'apellidos' => $fila['apellidos'],
                    'estado' => 1,
                ]
            );
        }

        return $docentes;
    }

    /** @return array<string, Programa> indexados por nombre del curso */
    private function cursos(array $filas, array $docentes): array
    {
        $cursos = [];

        foreach ($filas as $fila) {
            $curso = Programa::create([
                'grado' => TipoOferta::Curso->grado(),
                'nombre' => $fila['nombre'],
                'slug' => Str::slug($fila['nombre']),
                'modalidad' => $fila['modalidad'],
                'horas_academicas' => $fila['horas_academicas'],
                'sumilla' => $fila['sumilla'],
                'estado' => Programa::ESTADO_PUBLICADO,
                // `grado_otorga` es un par «Rótulo: contenido» de texto libre, no
                // un grado académico (ver Programa::denominacionGradoOtorga). Se
                // aprovecha para la escuela, que no tiene columna propia.
                'grado_otorga_label' => 'Escuela',
                'grado_otorga' => $fila['escuela'],
            ]);

            if ($docente = $docentes[$fila['docente']] ?? null) {
                $curso->docentes()->attach($docente->id, [
                    'es_coordinador' => true,
                    'rol' => 'Responsable',
                    'orden' => 1,
                ]);
            }

            $cursos[$fila['nombre']] = $curso;
        }

        return $cursos;
    }

    private function cronograma(array $filas, array $cursos): void
    {
        $settings = AdmisionSetting::firstOrCreate(
            ['tipo' => TipoOferta::Curso->value],
            [
                'hero_titulo' => 'Programación 2026',
                'hero_subtitulo' => 'Sección Cursos · CERSEU',
            ]
        );

        foreach ($filas as $fila) {
            AdmisionCronogramaItem::create([
                'admision_setting_id' => $settings->id,
                'programa' => $fila['programa'],
                'convocatoria' => $fila['convocatoria'],
                'fecha_inscripcion' => $fila['fecha_inscripcion'],
                'fecha_limite' => $fila['fecha_limite'],
                'estado' => $fila['estado'],
                'orden' => $fila['orden'],
            ]);
        }

        AdmisionSetting::clearCache(TipoOferta::Curso);
    }
}
