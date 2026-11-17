<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The page of clima y pulso (/clima-laboral). No law asks for it, so it cites none; what it says of anonymity comes from
 * app/Modules/Engagement/README.md.
 *
 * @phpstan-import-type Page from ToolPages
 */
final class Engagement
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'Encuesta de clima laboral anónima y pulsos',
            'description' => 'Haz tu encuesta de clima laboral y pulsos cortos: tu gente contesta desde su enlace y tú ves qué atender primero, sin ver ninguna respuesta individual.',
            'name' => 'Clima y pulso',
            'h1' => 'Encuesta de clima laboral y pulsos, sin ver quién contestó qué',
            'lead' => 'Tu gente contesta la encuesta de clima y pulsos cortos desde su enlace. Tú ves qué temas atender primero, los resultados por área y la comparación con la medición anterior, con los grupos de menos de 5 respuestas ocultos.',
            'norm' => null,
            'features' => [
                ['title' => 'Encuesta de clima con preguntas probadas', 'text' => 'Mide qué impulsa el compromiso de tu gente y te dice qué temas atender primero.'],
                ['title' => 'Pulsos cortos cada mes o cada trimestre', 'text' => 'Hasta 11 preguntas rápidas, sin preguntas abiertas, para ver si las acciones están funcionando. Se lanzan solos en la fecha que elijas.'],
                ['title' => 'Los grupos pequeños se ocultan', 'text' => 'Los resultados se ven por centro, área, antigüedad o jefe, y un grupo con menos de 5 respuestas no se muestra. Nadie ve una respuesta individual.'],
                ['title' => 'Compara y sigue el avance', 'text' => 'Compara una medición con la anterior y un centro con otro, y registra los compromisos que tomas con tu gente.'],
            ],
            'audience' => [
                'company' => 'RH lanza la encuesta y los pulsos, y lee los resultados por grupo. Cada jefe ve en su portal los resultados de su equipo, nunca los comentarios originales.',
                'consultant' => 'Si asesoras a varias empresas, llevas el clima de cada una por separado desde la misma cuenta y les entregas sus resultados.',
            ],
            'privacy' => 'Las respuestas se guardan sin ningún dato que lleve a la persona: ni su nombre, ni su invitación, ni la hora en que contestó. Los grupos de menos de 5 respuestas se ocultan y nadie en la empresa ve una respuesta individual.',
            'faqs' => [
                ['question' => '¿La encuesta de clima laboral es anónima?', 'answer' => 'Sí. La respuesta se guarda sin ninguna llave hacia la persona ni hora de envío; la invitación solo registra que ya contestó. RH ve resultados agregados, nunca una respuesta individual.'],
                ['question' => '¿Qué diferencia hay entre la encuesta de clima y un pulso?', 'answer' => 'La encuesta de clima es completa y te dice qué atender primero. El pulso es corto, de hasta 11 preguntas sin preguntas abiertas, y se repite cada mes o cada trimestre para ver si las acciones funcionan.'],
                ['question' => '¿Puedo ver los resultados por área?', 'answer' => 'Sí, por centro de trabajo, área, antigüedad o jefe. Un grupo con menos de 5 respuestas no se muestra, para que nadie pueda deducir quién contestó qué.'],
                ['question' => '¿Quién lee los comentarios?', 'answer' => 'RH lee los comentarios originales solo cuando hay 10 o más. El jefe nunca los lee: a lo más recibe un resumen por temas.'],
                ['question' => '¿Puedo comparar con la medición anterior?', 'answer' => 'Sí. Comparas una ola con la anterior y un centro con otro. La tendencia de una pregunta muestra flechas solo cuando hay 30 respuestas o más en las dos olas.'],
            ],
            'film' => [
                'name' => 'humetria-engagement',
                'title' => 'Clima y pulso en Humetría',
                'description' => 'La persona contesta la encuesta de clima y un pulso corto desde su enlace, y RH ve qué temas atender primero, los resultados por área con los grupos de menos de 5 respuestas ocultos y la comparación de una ola con la anterior y de un centro con otro, sin ninguna respuesta individual.',
                'duration' => 31,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
