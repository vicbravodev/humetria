<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The public page of Clima y pulso (/clima-laboral). What it says of the law comes from app/Modules/Engagement/README.md; its
 * screenshots are the demo company's, in public/images/tools.
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
            'family' => 'Escucha a tu gente',
            'chip' => 'Respuestas sin nombre ni hora · grupos de 5 o más',
            'h1' => 'Entérate de qué pasa con tu gente antes de que alguien renuncie',
            'lead' => 'Una encuesta de clima que te dice qué atender primero y pulsos cortos para ver si tus acciones funcionan. Tu gente contesta desde su enlace; tú ves resultados por área y la tendencia, sin ver nunca una respuesta individual.',
            'proof' => 'Preguntas probadas, respuestas sin nombre ni hora, y ningún grupo de menos de 5 respuestas a la vista.',
            'norm' => null,
            'guideLink' => false,
            'pick' => null,
            'steps' => [
                [
                    'title' => 'Lanzas la encuesta de clima',
                    'text' => 'Con preguntas probadas sobre compromiso, liderazgo, crecimiento y más, para toda la plantilla o un centro. Los pulsos se lanzan solos en la fecha que elijas.',
                    'shot' => 'engagement-08-launch',
                    'alt' => 'Una encuesta de clima lista para lanzar',
                ],
                [
                    'title' => 'Tu gente contesta desde su enlace',
                    'text' => 'Desde su celular, sin cuenta y sin que su respuesta lleve su nombre ni la hora en que contestó.',
                    'shot' => 'engagement-11-survey-question-mobile',
                    'alt' => 'Una pregunta del pulso en el celular',
                ],
                [
                    'title' => 'Ves qué atender primero',
                    'text' => 'Los temas que más mueven el compromiso de tu gente, por centro, área, antigüedad o jefe, con los grupos de menos de 5 ocultos.',
                    'shot' => 'engagement-02-climate-drivers',
                    'alt' => 'Los temas que atender primero',
                ],
            ],
            'features' => [
                [
                    'kicker' => 'Prioridades',
                    'title' => 'Qué temas atender primero, no solo un promedio',
                    'text' => 'Humetría cruza qué tan bien sale cada tema con cuánto mueve el compromiso, y te dice dónde actuar.',
                    'bullets' => [
                        'Fortalezas y oportunidades, en ese orden',
                        'Índice de compromiso y de recomendación',
                        'Cada tema con sus preguntas detrás',
                    ],
                    'shot' => 'engagement-02-climate-drivers',
                    'alt' => 'Temas con prioridad alta y fortalezas',
                    'caption' => 'Dónde actuar primero. Fortalezas y oportunidades.',
                ],
                [
                    'kicker' => 'Por grupo',
                    'title' => 'Dónde está el problema, sin exponer a nadie',
                    'text' => 'El mapa de calor por centro, área, antigüedad o jefe. Un grupo con menos de 5 respuestas aparece como protegido.',
                    'bullets' => [
                        'Un corte a la vez, para no reconstruir grupos chicos',
                        'El mínimo se aplica después de cada filtro',
                        'Los comentarios solo se leen con 10 o más',
                    ],
                    'shot' => 'engagement-03-climate-groups',
                    'alt' => 'Mapa de calor de temas por área',
                    'caption' => 'Favorables por tema y área. Grupos chicos: protegidos.',
                ],
                [
                    'kicker' => 'Tendencia',
                    'title' => 'Si tus acciones funcionan, se ve en el pulso',
                    'text' => 'Compara una medición con la anterior y un centro con otro, y registra los compromisos que tomas con tu gente.',
                    'bullets' => [
                        'La tendencia del compromiso ola por ola',
                        'Flechas de cambio solo con muestras suficientes',
                        'Compromisos visibles para tu gente en su portal',
                    ],
                    'shot' => 'engagement-05-pulse-trend',
                    'alt' => 'Tendencia del pulso de julio a octubre',
                    'caption' => 'Compromiso, ola por ola. Recomendación de +40 a +61.',
                ],
            ],
            'beforeAfterTitle' => 'De la encuesta anual que nadie cree a la escucha continua',
            'beforeAfter' => [
                [
                    'before' => 'Una encuesta en un formulario genérico que todos saben quién contestó',
                    'after' => 'Respuestas sin nombre, sin invitación y sin hora',
                ],
                [
                    'before' => 'Un promedio general que no dice qué hacer',
                    'after' => 'Los temas a atender primero',
                ],
                [
                    'before' => 'Resultados por área que exponen a equipos chicos',
                    'after' => 'Grupos de menos de 5: protegidos',
                ],
                [
                    'before' => 'Una medición al año, sin seguimiento',
                    'after' => 'Pulsos cortos que se lanzan solos',
                ],
            ],
            'audience' => [
                [
                    'who' => 'RH',
                    'title' => 'Sabe dónde actuar y si funcionó',
                    'text' => 'Lanza la encuesta y los pulsos, lee los resultados por grupo y registra compromisos.',
                ],
                [
                    'who' => 'Jefes',
                    'title' => 'Ven a su equipo, nunca a una persona',
                    'text' => 'Cada jefe ve en su portal los resultados de su equipo, nunca los comentarios originales.',
                ],
                [
                    'who' => 'La persona trabajadora',
                    'title' => 'Opina sin miedo',
                    'text' => 'Contesta desde su enlace y ve lo que la empresa se compromete a hacer.',
                ],
            ],
            'privacy' => [
                'title' => 'Ninguna respuesta lleva nombre ni hora',
                'items' => [
                    [
                        'title' => 'Sin llave a la persona',
                        'text' => 'La respuesta se guarda sin su nombre, sin su invitación y sin la hora en que contestó.',
                    ],
                    [
                        'title' => 'Grupos de 5 o más',
                        'text' => 'Un grupo con menos respuestas se oculta, después de cada filtro.',
                    ],
                    [
                        'title' => 'Comentarios con mínimo',
                        'text' => 'RH lee los comentarios originales solo cuando hay 10 o más; el jefe nunca los lee.',
                    ],
                    [
                        'title' => 'Nadie ve una respuesta',
                        'text' => 'Ni RH, ni el dueño, ni el jefe: solo resultados agregados.',
                    ],
                ],
            ],
            'faqs' => [
                [
                    'question' => '¿La encuesta de clima laboral es anónima?',
                    'answer' => 'Sí. La respuesta se guarda sin ninguna llave hacia la persona ni hora de envío; la invitación solo registra que ya contestó.',
                ],
                [
                    'question' => '¿Qué diferencia hay entre la encuesta de clima y un pulso?',
                    'answer' => 'La encuesta de clima es completa y te dice qué atender primero. El pulso es corto, de hasta 11 preguntas, y se repite cada mes o trimestre.',
                ],
                [
                    'question' => '¿Puedo ver los resultados por área?',
                    'answer' => 'Sí, por centro, área, antigüedad o jefe. Un grupo con menos de 5 respuestas no se muestra.',
                ],
                [
                    'question' => '¿Quién lee los comentarios?',
                    'answer' => 'RH lee los comentarios originales solo cuando hay 10 o más. El jefe nunca los lee.',
                ],
                [
                    'question' => '¿Puedo comparar con la medición anterior?',
                    'answer' => 'Sí: una ola con la anterior y un centro con otro. La tendencia muestra flechas solo con 30 respuestas o más en las dos olas.',
                ],
            ],
            'related' => [
                [
                    'key' => 'nom_035',
                    'why' => 'El entorno organizacional también lo pide la norma.',
                ],
                [
                    'key' => 'complaints',
                    'why' => 'Para lo que no puede esperar al siguiente pulso.',
                ],
                [
                    'key' => 'org_chart',
                    'why' => 'Los resultados por jefe salen de quién reporta a quién.',
                ],
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
