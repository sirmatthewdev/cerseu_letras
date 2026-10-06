<?php

use App\Models\Docente;
use App\Models\Programa;
use Illuminate\Database\Migrations\Migration;

/**
 * Sumillas oficiales de los cursos de extensión universitaria, 2026.
 *
 * Texto entregado por la Unidad. Hasta ahora las fichas mostraban una sumilla
 * generada a partir de las horas y la modalidad —«Curso de 12 horas académicas
 * en modalidad presencial, a cargo de…»—, que describía el formato pero no el
 * contenido. Esto lo reemplaza por lo que el curso enseña de verdad.
 *
 * Va como migración y no como seeder porque es carga puntual de contenido: un
 * seeder se ejecuta en cada instalación limpia y compite con el que ya siembra
 * los 39 programas. Aquí se aplica una vez y queda registrada.
 *
 * **En una instalación limpia esto no aplica ninguna sumilla**, y es correcto:
 * las migraciones corren antes que los seeders, así que no hay ninguna ficha
 * que actualizar y el bucle pasa de largo. Para que el texto oficial llegue
 * igual a una instalación nueva, las siete sumillas están también en
 * `database/seeders/data/oferta-cerseu-2026.json`, que es de donde las toma
 * `OfertaCerseuSeeder`. Son el mismo texto en dos sitios a propósito: aquí para
 * las instalaciones que ya existían, allí para las que empiezan de cero. Si se
 * corrige una, hay que corregir la otra.
 *
 * Los cinco cursos de `NUEVOS` sí se crean siempre, y en una instalación limpia
 * son los únicos que esta migración deja: cinco borradores que el seeder de la
 * oferta debe ignorar. Durante un tiempo no los ignoró —se creía cargado al ver
 * cualquier curso— y la instalación se quedaba sin los 39.
 *
 * **El emparejamiento se hizo por expositor, no por título.** Los títulos del
 * documento no son literales —«Ortografía y Redacción General» frente a
 * «Redacción y Ortografía I»— y emparejar por parecido habría publicado la
 * descripción equivocada en alguna ficha. Cada actualización de aquí abajo se
 * comprobó contra el docente que la imparte.
 *
 * Un caso quedó sin resolver y NO se toca: «Normas APA», que el documento
 * atribuye a Rolando Rocha. En la base hay «Normas APA I» (que imparte Mamani
 * Quispe) y «Curso-taller: APA sin clichés» (que imparte Rocha): el título
 * apunta a una y el expositor a la otra. Lo decidió la Unidad después, a
 * favor de la ficha de Rocha: lo resuelve la migración
 * `2026_09_07_110000_sumilla_de_normas_apa_al_curso_de_rocha`.
 */
return new class extends Migration
{
    /**
     * Sumillas para fichas que ya existen, por id.
     *
     * El id y no el nombre: los nombres se editan desde el panel, y esta
     * migración tiene que seguir apuntando a la misma ficha aunque alguien
     * corrija una tilde.
     *
     * @var array<int, string>
     */
    private const SUMILLAS = [
        // 3 · Redacción y Ortografía I — Agustín Prado
        3 => 'En el curso se revisan puntualmente las normas ortográficas (uso de letras, acentuación y puntuación), el uso de conectores, el cuidado léxico para evitar las imprecisiones y la escritura de párrafos según la normativa de la RAE. Se redactan textos expositivos y argumentativos que son la base para la escritura de textos académicos como artículos, ensayos o documentos institucionales.',

        // 22 · Oratoria y Teatro I — Leonardo Chihuán
        22 => 'Dictado por el Campeón Mundial y Nacional de Oratoria, Leonardo Chihuán, con 13 años de experiencia docente. El curso busca que los alumnos superen el miedo a hablar en público y desarrollen técnicas de comunicación oral para impactar en exposiciones académicas, laborales, sociales y cotidianas. Se emplea el teatro como herramienta de expresión escénica y artística, y se enseña a argumentar con profundidad para lograr discursos persuasivos. Al finalizar, los estudiantes participan en una ceremonia de graduación donde pueden mostrar sus habilidades ante familiares y amigos.',

        // 31 · Investigación cuantitativa, cualitativa y mixta — Roberto Katayama
        31 => 'Este curso fortalece las competencias de estudiantes de últimos ciclos, egresados y docentes en la elaboración de trabajos de suficiencia profesional y tesis de pregrado bajo los estándares de la investigación científica. Enfoques abordados: cuantitativo, cualitativo y mixto. Contenidos principales: selección del tema, formulación del problema, diseño metodológico, elaboración de instrumentos, análisis de resultados y redacción del informe final. Áreas de aplicación: ciencias humanas, ciencias sociales, ciencias jurídicas, educación, administrativas y psicología. Los participantes obtendrán una base metodológica sólida para desarrollar y asesorar investigaciones académicas con rigor científico.',

        // 30 · Introducción a la Política de Aristóteles — Dante Dávila
        30 => 'El seminario es una iniciación en el estudio de una de las obras clásicas de la filosofía política y la filosofía práctica, la «Política» de Aristóteles. Por ello, busca brindar una visión de conjunto de la obra: a partir del estudio de algunos pasajes seleccionados, se estudiarán los problemas centrales del texto, se discutirá la consistencia de sus planteamientos y se pondrá de manifiesto su sorprendente actualidad. El seminario recorre los principales libros de la obra y esclarece conceptos como: la comunidad política y la comunidad familiar, el problema de la esclavitud, la teoría de las constituciones y las formas de gobierno, las causas de las revoluciones y la ciudad ideal, entre otros.',

        // 25 · Didática do Português como Língua Pluricêntrica — Maíra Mendes Magela y Marco Lovón
        25 => 'Este taller, organizado por el Centro de Responsabilidad Social y Extensión Universitaria (CERSEU-FLCH) y el Instituto de Investigaciones de Lingüística Aplicada (CILA), aborda los principios teóricos y prácticos para la enseñanza del portugués desde una perspectiva pluricéntrica, considerando la diversidad de normas y variedades presentes en Portugal, Brasil y los países africanos lusófonos. Se orienta a futuros y actuales docentes, brindando herramientas didácticas para trabajar la variación lingüística, la interculturalidad y la mediación comunicativa en el aula. A través de actividades comparativas, análisis de materiales auténticos y prácticas pedagógicas, el curso desarrolla competencias para enseñar un portugués inclusivo, contextualizado y alineado con las demandas contemporáneas de la educación en lenguas.',

        // 2 · Curso-taller de elaboración de preguntas de opción múltiple — Luis Mamani Quispe
        2 => 'Este curso, de modalidad semipresencial, tiene como objetivo que el participante desarrolle la capacidad de elaborar preguntas de opción múltiple en distintos formatos y niveles de complejidad. Se abordan los principios para diseñar ítems que permitan evaluar habilidades en diversos niveles cognitivos, las fases de su construcción y la variedad de formatos disponibles, brindando una formación integral en la elaboración de instrumentos de evaluación.',
    ];

    /**
     * Cursos del documento que todavía no existen como ficha.
     *
     * Se crean como BORRADOR, sin horas ni modalidad: el documento no las trae y
     * inventarlas sería publicar un dato que nadie ha dado. En borrador no salen
     * en el sitio, y la Unidad puede verlos con «Vista previa» antes de
     * completarlos y publicarlos.
     *
     * @var list<array{nombre: string, sumilla: string, expositores: list<array{nombres: string, apellidos: string, grado: string}>}>
     */
    private const NUEVOS = [
        [
            'nombre' => 'Proyecto de enseñanza: Metodología Activa · Instructor en Inglés como Lengua Extranjera (L2)',
            'sumilla' => 'Este curso tiene como objetivo el entrenamiento y actualización de los alumnos que culminaron el nivel avanzado del idioma inglés, tanto en el Centro de Idiomas de San Marcos como en instituciones privadas, quienes quieren llegar a entender la metodología de la enseñanza activa del inglés centrada en el alumno, a fin de contar con un recurso humano calificado. Para lograr este objetivo se trabaja con los recursos educativos proveídos por RELO Andes de la Embajada de los Estados Unidos y el Consejo Británico. El curso tiene una duración de un año y los alumnos podrán enrolarse en cualquier mes; una vez lo hagan, deberán cumplir un año para completar las materias. No tiene prerrequisitos. Malla curricular: Fonética y Fonología del Inglés I y II, Didáctica de la Enseñanza del Inglés Adultos I y II, Didáctica de la Enseñanza del Inglés Niños I y II, Gramática Avanzada del Inglés I y II, Composición Avanzada del Inglés I y II, y Práctica de la Enseñanza del Inglés I y II.',
            'expositores' => [
                ['nombres' => 'Yoni', 'apellidos' => 'Cárdenas Cornelio', 'grado' => 'Dra.'],
            ],
        ],
        [
            'nombre' => 'Encuentros Literarios Francófonos y concurso final: Choix Goncourt del Perú, edición 2026',
            'sumilla' => 'El Premio Goncourt, máximo galardón literario francés, inspiró la creación del Choix Goncourt, jurado estudiantil que desde hace más de 20 años elige su propio ganador entre las novelas finalistas. Hoy participan 22 países. En América Latina existen ediciones en Brasil, Uruguay–Argentina (Río de la Plata) y Perú, siendo este último el segundo certamen hispanohablante. La UNMSM organiza la segunda edición peruana, donde grupos de estudiantes leen y debaten las obras finalistas durante tres meses, en formato de club de lectura, con acompañamiento de referentes académicos. El objetivo es fomentar un acercamiento crítico y lúdico a la literatura contemporánea francesa, integrando análisis cultural, histórico y estilístico.',
            'expositores' => [
                ['nombres' => 'Guillaume', 'apellidos' => 'Oisel', 'grado' => 'Dr.'],
                ['nombres' => 'David', 'apellidos' => 'Duponchel', 'grado' => 'Dr.'],
                ['nombres' => 'Stephanie', 'apellidos' => 'Borios', 'grado' => 'Dra.'],
            ],
        ],
        [
            'nombre' => 'Taller Cultivando la Filosofía',
            'sumilla' => 'La Cátedra Augusto Salazar Bondy impulsa este proyecto frente al desinterés por la lectura y la falta de reflexión crítica en estudiantes de secundaria. El taller busca fomentar el pensamiento crítico y filosófico mediante el diálogo, la creatividad y la argumentación, promoviendo respeto, tolerancia y autonomía intelectual. A lo largo de 6 sesiones de filosofía, los participantes desarrollan capacidades de comprensión y análisis para construir una visión humanística de la realidad. El proceso incluye una prueba diagnóstica inicial y una evaluación final de avances.',
            'expositores' => [
                ['nombres' => 'Verónica Matilde', 'apellidos' => 'Sánchez Montenegro', 'grado' => 'Dra.'],
            ],
        ],
        [
            'nombre' => 'El Trabajo de Campo en la Investigación Lingüística',
            'sumilla' => 'Este evento se centra en el trabajo de campo en la investigación de lenguas naturales. El objetivo fundamental es fortalecer las competencias investigativas a nivel de pregrado y posgrado. Mediante la revisión de casos de estudio, se proporcionarán los instrumentos técnicos y metodológicos requeridos para promover la exhaustiva documentación de las lenguas indígenas del Perú, una tarea medular.',
            'expositores' => [
                ['nombres' => 'Jairo', 'apellidos' => 'Valqui Culqui', 'grado' => 'Dr.'],
                ['nombres' => 'Heinrich', 'apellidos' => 'Helberg', 'grado' => 'Dr.'],
            ],
        ],
        [
            'nombre' => 'Narrativa Visual: Taller de Dirección de Arte para Cine',
            'sumilla' => 'El curso aborda la Dirección de Arte y la narrativa visual en el cine, explorando cómo los elementos plásticos —color, forma y textura— contribuyen a la construcción estética de objetos y personajes. Se profundiza en el diseño de vestuario, maquillaje y peinados, así como en la conceptualización y ambientación de los espacios escénicos. Además, se trabaja en herramientas creativas como el mood book y el pitch visual, culminando con la elaboración y presentación de una propuesta integral de arte.',
            'expositores' => [
                ['nombres' => 'José Ernesto', 'apellidos' => 'Ventocilla Maestre', 'grado' => 'Dr.'],
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::SUMILLAS as $id => $sumilla) {
            $programa = Programa::find($id);

            // Si la ficha ya no está —alguien la borró, o esta migración corre
            // sobre una instalación sembrada de otra forma—, se pasa de largo en
            // vez de fallar: una sumilla que no encuentra su ficha no es motivo
            // para detener un despliegue.
            if (! $programa) {
                continue;
            }

            $programa->update(['sumilla' => $sumilla]);
        }

        foreach (self::NUEVOS as $nuevo) {
            $programa = Programa::firstOrCreate(
                ['nombre' => $nuevo['nombre']],
                [
                    'grado' => 'Curso',
                    'sumilla' => $nuevo['sumilla'],
                    // Borrador: sin horas ni modalidad, que el documento no
                    // trae. No sale en el sitio hasta que la Unidad lo complete.
                    'estado' => Programa::ESTADO_BORRADOR,
                ]
            );

            foreach ($nuevo['expositores'] as $i => $expositor) {
                $docente = Docente::firstOrCreate(
                    ['nombres' => $expositor['nombres'], 'apellidos' => $expositor['apellidos']],
                    [
                        'grado' => $expositor['grado'],
                        // Igual que el curso: no visible hasta que la Unidad
                        // complete la ficha. Publicar a alguien en la plana
                        // docente con solo su nombre no le hace ningún favor.
                        'estado' => 0,
                    ]
                );

                $programa->docentes()->syncWithoutDetaching([
                    $docente->id => ['orden' => $i, 'es_coordinador' => $i === 0],
                ]);
            }
        }
    }

    public function down(): void
    {
        // Las sumillas anteriores no se restauran: eran texto generado a partir
        // de las horas y la modalidad, no contenido que nadie escribiera. Se
        // vacían, y el sitio vuelve a omitir el bloque.
        foreach (array_keys(self::SUMILLAS) as $id) {
            Programa::where('id', $id)->update(['sumilla' => null]);
        }

        foreach (self::NUEVOS as $nuevo) {
            $programa = Programa::where('nombre', $nuevo['nombre'])->first();

            if (! $programa) {
                continue;
            }

            $programa->docentes()->detach();
            $programa->forceDelete();

            foreach ($nuevo['expositores'] as $expositor) {
                // Solo se retira al docente si no quedó enganchado a nada más:
                // si la Unidad lo asignó a otro curso mientras tanto, borrarlo
                // se llevaría por delante ese trabajo.
                $docente = Docente::where('nombres', $expositor['nombres'])
                    ->where('apellidos', $expositor['apellidos'])
                    ->first();

                if ($docente && $docente->programas()->count() === 0) {
                    $docente->forceDelete();
                }
            }
        }
    }
};
