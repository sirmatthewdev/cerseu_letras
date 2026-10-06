<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // `site_settings` es de una sola fila: el modelo lo impide en `creating`,
        // y un `create` a secas rompía la cadena de seeders. Pero salir sin más
        // dejaba el seeder inerte: la migración ya inserta la fila, así que este
        // contenido no llegaba nunca. Se rellena la fila existente mientras
        // conserve el nombre que puso la migración —señal de que nadie la ha
        // tocado desde el panel— y se respeta cualquier edición posterior.
        $settings = SiteSetting::query()->first();

        if ($settings && $settings->site_name !== 'CERSEU - Facultad de Letras y Ciencias Humanas') {
            return;
        }

        $datos = [
            'site_name' => 'CERSEU de Letras',
            'site_description' => 'CERSEU de la Facultad de Letras y Ciencias Humanas UNMSM: cursos de capacitación en humanidades abiertos a toda la comunidad.',
            'logo_path' => null,
            'favicon_path' => null,
            'primary_color' => '#143B63',
            'secondary_color' => '#B6A350',
            // Textos de presentación enviados por el CERSEU.
            'home_hero_kicker' => 'Universidad Nacional Mayor de San Marcos · Decana de América',
            'home_hero_titulo' => 'CERSEU – Facultad de Letras',
            'home_hero_texto' => 'Abrimos nuestras puertas a toda la comunidad. Ofrecemos cursos de capacitación en humanidades, diseñados para fortalecer tu formación cultural, académica y profesional.',

            // El destino principal es Cursos, no Talleres: es la unica de las
            // tres secciones con oferta publicada. Mandar la portada a una
            // seccion vacia es la peor primera impresion posible.
            'home_hero_cta1_texto' => 'Ver cursos',
            'home_hero_cta1_url' => '/cursos',
            'home_hero_cta2_texto' => 'Cómo inscribirte',
            'home_hero_cta2_url' => '/admision',
            'cursos_hero_titulo' => 'Cursos',
            'cursos_hero_texto' => 'CERSEU – Facultad de Letras, UNMSM abre sus puertas a toda la comunidad. Ofrecemos cursos de capacitación en humanidades, diseñados para fortalecer tu formación cultural, académica y profesional.',
            'cursos_hero_claim' => 'Con docentes de la UNMSM, el conocimiento humanístico al alcance de todos.',
            'talleres_hero_titulo' => 'Talleres',
            'talleres_hero_texto' => 'Formación breve y práctica en humanidades, abierta a toda la comunidad.',
            'talleres_hero_claim' => 'Con docentes de la UNMSM, el conocimiento humanístico al alcance de todos.',
            'header_text' => 'CERSEU de Letras',
            'footer_text' => '© 2026 CERSEU de Letras y Ciencias Humanas. Todos los derechos reservados.',
            'email' => 'cerseu.letras@unmsm.edu.pe',
            'email_admision' => 'cerseu.letras@unmsm.edu.pe',
            'telefono' => '914 033 129',
            'anexo' => '2808',
            'direccion' => 'Av. Universitaria 1801, San Miguel, Lima 15088',
            'horario_atencion' => 'Lunes a Viernes: 9:00 AM - 5:00 PM',
            'facebook' => 'https://www.facebook.com/p/Cerseu-Letras-UNMSM-61558727160131/',
            'instagram' => 'https://www.instagram.com/cerseuletras/',
            'twitter' => null,
            'linkedin' => null,
            'youtube' => null,
            'tiktok' => 'https://www.tiktok.com/@cerseu.letras.unm',
            'web_facultad' => 'https://letras.unmsm.edu.pe/',
            'directorio_facultad' => 'https://cerseuletras.unmsm.edu.pe/directorio',
        ];

        $settings ? $settings->update($datos) : SiteSetting::create($datos);
    }
}
