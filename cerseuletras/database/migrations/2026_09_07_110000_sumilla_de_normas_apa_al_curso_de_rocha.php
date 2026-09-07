<?php

use App\Models\Programa;
use Illuminate\Database\Migrations\Migration;

/**
 * La sumilla de «Normas APA», resuelta.
 *
 * La carga de sumillas oficiales de 2026 dejó este caso sin tocar a propósito.
 * El documento titula el curso «Normas APA» y lo atribuye a Rolando Rocha, y en
 * la base hay dos candidatas: «Normas APA I» (id 23, que imparte Mamani
 * Quispe) y «Curso-taller: APA sin clichés» (id 29, que imparte Rocha). El
 * título apuntaba a una y el expositor a la otra, así que emparejar era
 * adivinar y se dejó para que lo decidiera la Unidad.
 *
 * La Unidad eligió la ficha de Rocha, que además es la más reciente de las dos.
 * Eso mantiene el criterio con el que se emparejó todo lo demás —por expositor
 * y no por parecido del título—, así que la excepción desaparece.
 *
 * La id 23 no se toca: sigue con la sumilla generada a partir de sus horas y su
 * modalidad, porque el documento no trae texto para ella.
 */
return new class extends Migration
{
    /** «Curso-taller: APA sin clichés: más allá de la norma en la producción investigativa». */
    private const CURSO = 29;

    /*
     * Los «Contenidos principales» del documento van corridos dentro del
     * párrafo, como en el resto de sumillas cargadas: la ficha del sitio pinta
     * la sumilla como prosa, y una lista pegada en crudo se leería como un
     * renglón larguísimo lleno de saltos perdidos.
     */
    private const SUMILLA = 'Curso orientado al manejo actualizado de la normativa APA (7.ª edición) y la redacción académica, abordando sus innovaciones y su aplicación adecuada en trabajos de investigación. Contenidos principales: fundamentos del estilo APA y tipos de textos académicos, accesibilidad y formato en documentos académicos, redacción y estructura de artículos científicos, citación y paráfrasis sin plagio, normas de estilo y gramática en español, y elaboración de tablas, figuras y referencias.';

    public function up(): void
    {
        Programa::where('id', self::CURSO)->update(['sumilla' => self::SUMILLA]);
    }

    public function down(): void
    {
        // Igual que en la carga anterior: lo que había era texto generado a
        // partir de las horas y la modalidad, no algo que nadie escribiera. Se
        // vacía, y la ficha vuelve a omitir el bloque.
        Programa::where('id', self::CURSO)->update(['sumilla' => null]);
    }
};
