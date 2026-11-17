<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The page of the NOM-035 (/nom-035). What it says of the norm comes from docs/normas/nom-035/criterios.md and
 * docs/normas/nom-035/texto-de-referencia.md.
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
            'h1' => 'Evaluación NOM-035 en línea, por centro de trabajo y con respuestas anónimas',
            'lead' => 'Humetría elige las guías que le tocan a cada centro, sortea la muestra, invita a tu gente y le recuerda a quien falta. Las respuestas de las Guías II y III son anónimas, y al cerrar tienes los resultados, el informe que pide la norma y el borrador del Programa de intervención.',
            'norm' => [
                'title' => 'Qué pide la norma',
                'lead' => 'La NOM-035-STPS-2018, publicada en el DOF el 23 de octubre de 2018, pide identificar y analizar los factores de riesgo psicosocial y evaluar el entorno organizacional de cada centro de trabajo.',
                'rules' => [
                    ['text' => 'Las guías dependen de cuántas personas trabajan en el centro: hasta 15, solo la Guía I; de 16 a 50, las Guías I y II; más de 50, las Guías I y III.', 'source' => 'NOM-035-STPS-2018, numerales 2 y 7.1'],
                    ['text' => 'Identificar a quienes sufrieron o presenciaron un acontecimiento traumático severo, canalizarlos para su atención y llevar el registro de sus nombres.', 'source' => 'NOM-035-STPS-2018, numerales 5.5 y 5.8 c)'],
                    ['text' => 'Tener por escrito una política de prevención de riesgos psicosociales y difundirla, junto con los resultados de la evaluación.', 'source' => 'NOM-035-STPS-2018, numerales 5.1 y 5.7'],
                    ['text' => 'Si los resultados dan riesgo Medio, Alto o Muy alto, atenderlo con un Programa de intervención que diga áreas, medidas, fechas, avance y responsable.', 'source' => 'NOM-035-STPS-2018, numerales 8.3 y 8.4; Tablas 4 y 7'],
                    ['text' => 'Repetir la evaluación al menos cada dos años y dejar el resultado disponible para consulta de las personas trabajadoras.', 'source' => 'NOM-035-STPS-2018, numerales 7.8 y 7.9'],
                ],
                'facts' => [
                    ['label' => 'Publicada en el DOF', 'value' => '23 de octubre de 2018'],
                    ['label' => 'Evaluación', 'value' => 'Al menos cada dos años, por centro de trabajo'],
                    ['label' => 'Evidencias', 'value' => 'Impresas o electrónicas, al menos un año (num. 10.4)'],
                ],
            ],
            'features' => [
                ['title' => 'Las guías que le tocan a cada centro', 'text' => 'Al lanzar, Humetría cuenta a las personas activas del centro y fija las guías según su tamaño. Las preguntas se presentan tal como están en el DOF, sin reescribirlas.'],
                ['title' => 'La muestra, calculada y sorteada', 'text' => 'Si eliges aplicar la Guía III a una muestra, la calcula con la ecuación de la norma, redondeada hacia arriba, y la sortea al azar por género en proporción a la plantilla.'],
                ['title' => 'Invitaciones y recordatorios', 'text' => 'Cada persona contesta desde su celular o su computadora con un enlace personal. Ves quién ya contestó y le recuerdas a quien falta, sin ver qué contestó nadie.'],
                ['title' => 'Guía I y canalización', 'text' => 'La lista de quienes requieren valoración clínica solo la ven el dueño y RH, y cada consulta queda en la bitácora. Ahí registras a dónde se canalizó a cada persona y cuándo.'],
                ['title' => 'Informe del numeral 7.7 y Programa', 'text' => 'Al cerrar, el informe se arma con los datos del centro, el método y los resultados, y se imprime o se guarda como PDF. Si hay riesgo Medio o más, recibes el borrador del Programa de intervención.'],
            ],
            'audience' => [
                'company' => 'RH lanza la evaluación de cada centro, sigue el avance, recibe el informe y ve la fecha límite de la siguiente evaluación.',
                'consultant' => 'Si asesoras a varias empresas, llevas la evaluación de cada una por separado desde la misma cuenta, cada razón social con su gente y su informe.',
            ],
            'privacy' => 'Las respuestas de las Guías II y III son anónimas: se guardan sin ninguna relación con la persona y RH ve quién contestó, nunca qué. Ningún resultado se muestra para un grupo con menos de 5 respuestas. La Guía I lleva nombre porque la norma obliga a canalizar, y solo la ven los roles autorizados.',
            'faqs' => [
                ['question' => '¿Qué guías de la NOM-035 le tocan a mi empresa?', 'answer' => 'Depende de cuántas personas trabajan en cada centro de trabajo: hasta 15, solo la Guía I; de 16 a 50, las Guías I y II; más de 50, las Guías I y III (numerales 2 y 7.1). Cada razón social y cada centro se evalúan por separado.'],
                ['question' => '¿Cada cuánto se aplica la NOM-035?', 'answer' => 'La evaluación se repite al menos cada dos años (numeral 7.9). Además, según la STPS, la Guía I se aplica cada vez que alguien sufre o presencia un acontecimiento traumático severo durante o con motivo del trabajo.'],
                ['question' => '¿Puedo aplicar la Guía III a una muestra?', 'answer' => 'Sí. La norma permite una muestra representativa calculada con su Ecuación 1, elegida al azar y repartida entre hombres y mujeres según el porcentaje de cada género en el centro (Guía III, III.1). En un centro de 100 personas, la muestra es de 80. La Guía II, en cambio, se aplica a todos.'],
                ['question' => '¿Las respuestas de la NOM-035 son anónimas?', 'answer' => 'La norma pide proteger la privacidad y la confidencialidad de los datos. En Humetría las respuestas de las Guías II y III son anónimas. La Guía I es identificada, porque la norma obliga a canalizar a quien lo necesite y a registrar su nombre (numerales 5.5 y 5.8 c).'],
                ['question' => '¿Qué hago si salen niveles de riesgo Medio, Alto o Muy alto?', 'answer' => 'La norma pide atenderlos con un Programa de intervención (numerales 8.3 y 8.4, Tablas 4 y 7). Según la STPS, los resultados no se promedian: se analiza cada cuestionario, y por eso Humetría muestra cuántas personas quedan en cada nivel.'],
                ['question' => '¿Se puede aplicar la NOM-035 en línea?', 'answer' => 'Sí. La norma no exige papel ni aplicación presencial: las evidencias y registros pueden estar impresos o en medios electrónicos, y se conservan al menos un año (numeral 10.4).'],
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
