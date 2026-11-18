<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The public page of NOM-035 (/nom-035). What it says of the law comes from docs/normas/nom-035/; its
 * screenshots are the demo company's, in public/images/tools.
 *
 * @phpstan-import-type Page from ToolPages
 */
final class Nom035
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'Software NOM-035: evaluación con Guías I, II y III',
            'description' => 'Aplica la NOM-035 en línea por centro de trabajo: las guías que te tocan, la muestra, respuestas anónimas y el informe del numeral 7.7.',
            'name' => 'NOM-035',
            'family' => 'Cumple la norma',
            'chip' => 'NOM-035-STPS-2018 · por centro de trabajo',
            'h1' => 'Del cuestionario al informe para la STPS, sin capturar una sola respuesta',
            'lead' => 'Humetría elige las guías que le tocan a cada centro, sortea la muestra e invita a tu gente a contestar desde su celular. Las respuestas de las Guías II y III son anónimas, y al cerrar tienes los resultados, el informe del numeral 7.7 y el borrador del Programa de intervención.',
            'proof' => 'Las preguntas van tal como están en el DOF. RH ve quién contestó las Guías II y III, nunca qué, y ningún grupo de menos de 5 respuestas se muestra.',
            'norm' => [
                'title' => 'Qué pide la norma',
                'lead' => 'La NOM-035 pide identificar y prevenir los factores de riesgo psicosocial en cada centro de trabajo. Lo que te toca depende de cuántas personas trabajan ahí.',
                'facts' => [
                    [
                        'label' => 'Hasta 15 personas',
                        'value' => 'Guía I',
                        'note' => 'Numerales 2 y 7.1',
                    ],
                    [
                        'label' => 'De 16 a 50 personas',
                        'value' => 'Guías I y II',
                        'note' => 'Por centro de trabajo',
                    ],
                    [
                        'label' => 'Más de 50 personas',
                        'value' => 'Guías I y III',
                        'note' => 'Al menos cada dos años · 7.9',
                    ],
                ],
                'rules' => [
                    [
                        'text' => 'Identificar a quienes sufrieron o presenciaron un acontecimiento traumático severo, canalizarlos para su atención y llevar el registro.',
                        'source' => 'Numerales 5.5 y 5.8 c)',
                    ],
                    [
                        'text' => 'Tener por escrito una política de prevención de riesgos psicosociales y difundirla, junto con los resultados de la evaluación.',
                        'source' => 'Numerales 5.1 y 5.7',
                    ],
                    [
                        'text' => 'Si los resultados dan riesgo Medio, Alto o Muy alto, atenderlo con un Programa de intervención con áreas, medidas, fechas, avance y responsable.',
                        'source' => 'Numerales 8.3 y 8.4; Tablas 4 y 7',
                    ],
                    [
                        'text' => 'Conservar las evidencias, impresas o electrónicas, al menos un año.',
                        'source' => 'Numeral 10.4',
                    ],
                ],
                'note' => 'La norma se publicó en el DOF el 23 de octubre de 2018.',
            ],
            'guideLink' => false,
            'pick' => [
                'title' => '¿Qué guías le tocan a tu centro?',
                'lead' => 'La NOM-035 se aplica por centro de trabajo, y lo que te toca depende de cuántas personas trabajan ahí. Humetría las cuenta al lanzar.',
                'question' => '¿Cuántas personas trabajan en el centro?',
                'options' => [
                    [
                        'label' => 'Hasta 15',
                        'answer' => 'Solo la Guía I',
                        'detail' => 'Acontecimientos traumáticos severos. Lleva nombre porque la norma obliga a canalizar.',
                    ],
                    [
                        'label' => 'De 16 a 50',
                        'answer' => 'Guías I y II',
                        'detail' => 'La Guía II identifica los factores de riesgo psicosocial, con respuestas anónimas.',
                    ],
                    [
                        'label' => 'Más de 50',
                        'answer' => 'Guías I y III',
                        'detail' => 'La Guía III también evalúa el entorno organizacional y puede aplicarse a una muestra.',
                    ],
                ],
            ],
            'steps' => [
                [
                    'title' => 'Lanzas la evaluación de un centro',
                    'text' => 'Humetría cuenta a las personas activas y fija las guías. Si eliges muestra para la Guía III, la calcula con la ecuación de la norma y la sortea por género.',
                    'shot' => 'nom-035-08-launch',
                    'alt' => 'El lanzamiento de una evaluación: las guías que le tocan al centro y la muestra',
                ],
                [
                    'title' => 'Tu gente contesta desde su celular',
                    'text' => 'Cada persona recibe un enlace de un solo uso. Ves el avance por área y recuerdas a quien falta, sin ver qué contestó nadie.',
                    'shot' => 'nom-035-10-survey-intro-mobile',
                    'alt' => 'El inicio de la encuesta NOM-035 en el celular',
                ],
                [
                    'title' => 'Cierras y tienes el informe',
                    'text' => 'Resultados por categoría y dominio, el informe del numeral 7.7 y, si hay riesgo Medio o más, el borrador del Programa.',
                    'shot' => 'nom-035-03-results',
                    'alt' => 'Los resultados de la evaluación por nivel de riesgo',
                ],
            ],
            'features' => [
                [
                    'kicker' => 'Avance en vivo',
                    'title' => 'Sabes quién falta por contestar, sin saber qué contestó',
                    'text' => 'La participación por centro y por área, día por día, para recordar a tiempo a quien falta.',
                    'bullets' => [
                        'Recordatorios a quien no ha contestado, con un clic',
                        'Las respuestas anónimas no tienen hora visible ni llave a la persona',
                        'La evaluación cierra sola en la fecha que elijas',
                    ],
                    'shot' => 'nom-035-02-open-campaign',
                    'alt' => 'Avance de la evaluación con respuestas por día y por área',
                    'caption' => '50 de 84 respuestas. Participación por área.',
                ],
                [
                    'kicker' => 'Resultados',
                    'title' => 'Dónde está el riesgo, por categoría y dominio',
                    'text' => 'Los niveles de riesgo con los puntos de corte de la norma, por centro, área o grupo, siempre con 5 respuestas o más.',
                    'bullets' => [
                        'Resultados por categoría, dominio y dimensión',
                        'Un grupo con menos de 5 respuestas se oculta, después de cada filtro',
                        'Los resultados no se promedian entre centros',
                    ],
                    'shot' => 'nom-035-03-results',
                    'alt' => 'Resultados de la NOM-035 con la distribución por nivel de riesgo',
                    'caption' => 'Distribución por nivel de riesgo. Guía I y Programa a un clic.',
                ],
                [
                    'kicker' => 'Para la inspección',
                    'title' => 'El informe del numeral 7.7, armado solo',
                    'text' => 'Datos del centro, método, resultados y conclusiones, listos para imprimir o guardar como PDF, junto con el Programa de intervención y su avance.',
                    'bullets' => [
                        'La canalización de la Guía I solo la ven el dueño y RH, con bitácora',
                        'El Programa lleva áreas, medidas, fechas, avance y responsable',
                        'El expediente junta la política, sus acuses y los resultados',
                    ],
                    'shot' => 'nom-035-05-report-domains',
                    'alt' => 'Informe del numeral 7.7 con la gráfica por categoría y dominio',
                    'caption' => 'Riesgo por categoría y dominio. Listo para imprimir o PDF.',
                ],
            ],
            'beforeAfterTitle' => 'De las hojas sueltas al expediente de cada centro',
            'beforeAfter' => [
                [
                    'before' => 'Cuestionarios en papel o en formularios genéricos',
                    'after' => 'Las guías del DOF, en el celular, con enlace de un solo uso',
                ],
                [
                    'before' => 'Capturar y calificar respuestas a mano',
                    'after' => 'Calificación con los puntos de corte de la norma',
                ],
                [
                    'before' => 'Un Excel con respuestas que cualquiera puede ver',
                    'after' => 'Respuestas anónimas, sin llave a la persona',
                ],
                [
                    'before' => 'Armar el informe al final, a contrarreloj',
                    'after' => 'El informe del numeral 7.7 al cerrar la evaluación',
                ],
            ],
            'audience' => [
                [
                    'who' => 'RH',
                    'title' => 'Cada centro con lo que le toca',
                    'text' => 'Lanzas, das seguimiento y cierras cada evaluación, con el Programa de intervención y su avance.',
                ],
                [
                    'who' => 'Consultores',
                    'title' => 'Varias empresas, un solo método',
                    'text' => 'Cada cliente con sus datos aparte y tu logo en los informes.',
                ],
                [
                    'who' => 'La persona trabajadora',
                    'title' => 'Contesta en su celular, en confianza',
                    'text' => 'Con un enlace que solo sirve una vez y sin que nadie en la empresa vea sus respuestas.',
                ],
            ],
            'privacy' => [
                'title' => 'Nadie en la empresa ve las respuestas de las Guías II y III',
                'items' => [
                    [
                        'title' => 'Guías II y III, anónimas',
                        'text' => 'Se guardan sin ninguna relación con la persona. RH ve quién contestó, nunca qué.',
                    ],
                    [
                        'title' => 'Grupos de 5 o más',
                        'text' => 'Ningún resultado se muestra para un grupo con menos respuestas, después de cada filtro.',
                    ],
                    [
                        'title' => 'Guía I, solo a quien debe',
                        'text' => 'Lleva nombre porque la norma obliga a canalizar; solo la ven los roles autorizados.',
                    ],
                    [
                        'title' => 'Sin hora exacta',
                        'text' => 'Nadie ve a qué hora contestó cada persona.',
                    ],
                ],
            ],
            'faqs' => [
                [
                    'question' => '¿Qué guías de la NOM-035 le tocan a mi empresa?',
                    'answer' => 'Depende de cuántas personas trabajan en cada centro: hasta 15, solo la Guía I; de 16 a 50, las Guías I y II; más de 50, las Guías I y III (numerales 2 y 7.1).',
                ],
                [
                    'question' => '¿Cada cuánto se aplica la NOM-035?',
                    'answer' => 'La evaluación se repite al menos cada dos años (numeral 7.9). Según la STPS, la Guía I se aplica cada vez que alguien sufre o presencia un acontecimiento traumático severo.',
                ],
                [
                    'question' => '¿Puedo aplicar la Guía III a una muestra?',
                    'answer' => 'Sí. La norma permite una muestra representativa calculada con su Ecuación 1, elegida al azar y repartida por género. Humetría la calcula y la sortea.',
                ],
                [
                    'question' => '¿Las respuestas son anónimas?',
                    'answer' => 'Las de las Guías II y III sí. La Guía I es identificada porque la norma obliga a canalizar, y solo la ven los roles autorizados.',
                ],
                [
                    'question' => '¿Qué hago si sale riesgo Medio, Alto o Muy alto?',
                    'answer' => 'La norma pide atenderlo con un Programa de intervención. Humetría te da el borrador y lleva su avance.',
                ],
                [
                    'question' => '¿Se puede aplicar la NOM-035 en línea?',
                    'answer' => 'Sí. La norma no exige papel: las evidencias pueden estar en medios electrónicos y se conservan al menos un año (numeral 10.4).',
                ],
            ],
            'related' => [
                [
                    'key' => 'complaints',
                    'why' => 'El mecanismo de quejas que pide el numeral 8.1.',
                ],
                [
                    'key' => 'engagement',
                    'why' => 'Mide si las acciones del Programa funcionan.',
                ],
                [
                    'key' => 'ley_silla',
                    'why' => 'Otra evaluación que revisa la STPS.',
                ],
            ],
            'film' => [
                'name' => 'humetria-nom-035',
                'title' => 'NOM-035 en Humetría',
                'description' => 'RH elige un centro y la plataforma marca qué guías le tocan según su tamaño, sortea la muestra por género, envía un recordatorio a quien falta sin ver qué contestó nadie y genera el informe del numeral 7.7, con la canalización de la Guía I solo para personas autorizadas.',
                'duration' => 32,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
