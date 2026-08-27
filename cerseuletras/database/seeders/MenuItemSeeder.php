<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * Vuelca el menú que estaba escrito en `navbar.blade.php`.
 *
 * Se conserva tal cual estaba en producción, incluidos los enlaces que caducan
 * por convocatoria: el objetivo de este seeder es que el sitio arranque igual
 * que antes, y a partir de ahí que el equipo los edite desde el panel.
 */
class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        if (MenuItem::query()->exists()) {
            $this->command?->info('El menú ya tiene elementos; no se toca.');

            return;
        }

        foreach ($this->menu() as $ordenPadre => $padre) {
            $hijos = $padre['hijos'] ?? [];
            unset($padre['hijos']);

            $item = MenuItem::create($padre + ['orden' => $ordenPadre]);

            foreach ($hijos as $ordenHijo => $hijo) {
                MenuItem::create($hijo + [
                    'parent_id' => $item->id,
                    'orden' => $ordenHijo,
                ]);
            }
        }

        MenuItem::clearCache();
    }

    private function menu(): array
    {
        return [
            [
                'etiqueta' => 'Nosotros', 'icono' => 'fas-info-circle',
                'hijos' => [
                    ['etiqueta' => 'Quiénes somos', 'route_name' => 'nosotros', 'icono' => 'fas-info-circle'],
                    ['etiqueta' => 'Directorio FLCH', 'url' => 'https://letras.unmsm.edu.pe/directorio/', 'icono' => 'fas-address-book', 'nueva_pestana' => true],
                    ['etiqueta' => 'Documentos y Recursos', 'route_name' => 'informativos.index', 'icono' => 'fas-file-alt'],
                    // «Grupos de Investigacion» apuntaba a la Unidad de
                    // Investigacion de la Facultad. Es de Letras, no del CERSEU.
                ],
            ],
            [
                'etiqueta' => 'Admisión', 'icono' => 'fas-user-plus',
                'hijos' => [
                    ['etiqueta' => 'Proceso de Admisión', 'route_name' => 'admision', 'icono' => 'fas-user-plus'],
                    // Aqui colgaban «Cuadro de Vacantes» y «Criterios de
                    // Evaluacion», dos PDF de posgrado.unmsm.edu.pe sobre la
                    // admision a maestrias y doctorados. Salian en la cabecera
                    // de todas las paginas de un sitio que no ofrece ninguna de
                    // las dos. Cuando el CERSEU publique sus equivalentes se
                    // anaden desde el panel, sin tocar este seeder.
                    ['etiqueta' => 'Cronograma Académico', 'route_name' => 'cronograma', 'icono' => 'fas-calendar-alt'],
                ],
            ],
            // La oferta del CERSEU, agrupada bajo un solo desplegable y de la
            // más corta a la más larga: talleres (horas académicas), cursos
            // (sesiones y horas) y especializaciones (módulos y meses).
            //
            // Los tres colgaban de la cabecera como entradas sueltas, que son
            // tres de las ocho posiciones de primer nivel gastadas en variantes
            // de una misma cosa. Agrupados, la cabecera baja a seis entradas y
            // la relación entre los tipos —que son la misma oferta a distinta
            // escala— queda dicha por la propia estructura del menú.
            //
            // «Formación» no lleva enlace propio: no existe una página que
            // reúna los tres tipos, y darle uno inventado llevaría a un destino
            // que no está construido.
            [
                'etiqueta' => 'Formación', 'icono' => 'fas-graduation-cap',
                'hijos' => [
                    ['etiqueta' => 'Talleres', 'route_name' => 'talleres.index', 'icono' => 'fas-certificate'],
                    ['etiqueta' => 'Cursos', 'route_name' => 'cursos.index', 'icono' => 'fas-graduation-cap'],
                    ['etiqueta' => 'Especializaciones', 'route_name' => 'especializaciones.index', 'icono' => 'fas-award'],
                ],
            ],
            [
                // Sin desplegable: su única subentrada apuntaba a esta misma
                // página, así que el menú se abría para ofrecer lo que ya
                // ofrecía la propia entrada.
                'etiqueta' => 'Trámites', 'icono' => 'fas-file-alt',
                'route_name' => 'tramites',
            ],
            [
                'etiqueta' => 'Actualidad', 'icono' => 'fas-newspaper',
                'hijos' => [
                    ['etiqueta' => 'Eventos', 'route_name' => 'eventos.index', 'icono' => 'fas-calendar-day'],
                    ['etiqueta' => 'Noticias', 'url' => 'https://letras.unmsm.edu.pe/categoria/noticias/', 'icono' => 'fas-newspaper', 'nueva_pestana' => true],
                    ['etiqueta' => 'Conferencias', 'url' => 'https://letras.unmsm.edu.pe/categoria/conferencias/', 'icono' => 'fas-microphone', 'nueva_pestana' => true],
                ],
            ],
            [
                // Los sitios hermanos de la Facultad, agrupados aquí. Antes
                // «Facultad» era un enlace suelto y CEID y OESI colían de un
                // desplegable «Idiomas» aparte, que se retira: los cuatro son
                // destinos externos del mismo ámbito.
                'etiqueta' => 'Facultad', 'icono' => 'fas-university',
                'url' => 'https://letras.unmsm.edu.pe',
                'nueva_pestana' => true,
                'hijos' => [
                    ['etiqueta' => 'Web Letras', 'url' => 'https://letras.unmsm.edu.pe/', 'icono' => 'fas-university', 'nueva_pestana' => true],
                    ['etiqueta' => 'Posgrado Letras', 'url' => 'https://posgradoletras.unmsm.edu.pe/', 'icono' => 'fas-graduation-cap', 'nueva_pestana' => true],
                    ['etiqueta' => 'CEID', 'url' => 'https://ceidletras.unmsm.edu.pe/', 'icono' => 'fas-language', 'nueva_pestana' => true],
                    ['etiqueta' => 'OESI', 'url' => 'https://letras.unmsm.edu.pe/oficina-de-examen-de-suficiencia-en-idiomas/', 'icono' => 'fas-file-circle-check', 'nueva_pestana' => true],
                ],
            ],
        ];
    }
}
