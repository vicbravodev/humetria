<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The public page of Ley Silla (/ley-silla). What it says of the law comes from docs/normas/ley-silla/; its
 * screenshots are the demo company's, in public/images/tools.
 *
 * @phpstan-import-type Page from ToolPages
 */
final class LeySilla
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'Ley Silla: obligaciones de la empresa y cómo cumplirlas',
            'description' => 'Qué obliga la Ley Silla y cómo llevarla: puestos de pie y sus asientos, encuesta confidencial, cláusulas para tu reglamento y el plan de acción.',
            'name' => 'Ley Silla',
            'family' => 'Cumple la norma',
            'chip' => 'En vigor desde el 17 de junio de 2025 · LFT, art. 132, fr. V',
            'h1' => 'Ley Silla: asientos, pausas y reglamento, con la evidencia a la mano',
            'lead' => 'Registras qué puestos se hacen de pie y dónde va su asiento, preguntas al personal de forma confidencial y Humetría te sugiere qué escribir en tu reglamento interior. Cada medida del plan guarda su responsable, su fecha y su evidencia.',
            'proof' => 'Sigue el flujograma de la STPS para ubicar cada asiento, pregunta al personal de forma confidencial y no borra nada del plan de acción.',
            'norm' => [
                'title' => 'Qué pide la ley',
                'lead' => 'La reforma a la Ley Federal del Trabajo obliga a dar asientos y descansos a quien trabaja de pie, y la STPS publicó cómo evaluarlo.',
                'facts' => [
                    [
                        'label' => 'En vigor desde',
                        'value' => '17 de junio de 2025',
                        'note' => 'Decreto DOF 19-12-2024',
                    ],
                    [
                        'label' => 'A quién aplica',
                        'value' => 'Servicios, comercio y análogos',
                        'note' => 'La industria, si el trabajo lo permite',
                    ],
                    [
                        'label' => 'Lo que prohíbe',
                        'value' => 'Estar de pie toda la jornada',
                        'note' => 'Art. 133, fr. XVII Bis',
                    ],
                ],
                'rules' => [
                    [
                        'text' => 'Proveer suficientes asientos o sillas con respaldo, a disposición de todas las personas, para trabajar o descansar periódicamente.',
                        'source' => 'LFT, art. 132, fr. V',
                    ],
                    [
                        'text' => 'Evaluar el riesgo de cada persona que trabaja de pie con siete condicionantes; la evaluación la hace la Comisión de Seguridad e Higiene.',
                        'source' => 'Disposiciones STPS, Quinta B, C y D',
                    ],
                    [
                        'text' => 'El reglamento interior debe incluir los periodos de reposo y las normas para usar los asientos.',
                        'source' => 'LFT, art. 423, fr. I y V',
                    ],
                    [
                        'text' => 'Informar los riesgos y las medidas, señalizar las áreas de asientos y canalizar a atención médica cuando haya síntomas.',
                        'source' => 'Disposiciones STPS, Quinta H, I y J',
                    ],
                ],
                'note' => 'Ni la ley ni las Disposiciones fijan cuántas sillas ni cuántas pausas: piden un número suficiente y acordarlo con las personas.',
            ],
            'guideLink' => false,
            'pick' => null,
            'steps' => [
                [
                    'title' => 'Registras los puestos de pie',
                    'text' => 'Por centro, con dónde va su asiento según el flujograma de la Figura 1 de la STPS.',
                    'shot' => 'ley-silla-07-standing-post',
                    'alt' => 'Un puesto de pie y dónde va su asiento, según la Figura 1',
                ],
                [
                    'title' => 'Preguntas y evalúas',
                    'text' => 'El personal contesta una encuesta confidencial y la Comisión evalúa a cada persona con las siete condicionantes.',
                    'shot' => 'ley-silla-04-survey',
                    'alt' => 'Los resultados de la encuesta confidencial por pregunta',
                ],
                [
                    'title' => 'Actúas con evidencia',
                    'text' => 'El plan de medidas y pausas, el texto para tu reglamento y lo que falta por cumplir, en un solo lugar.',
                    'shot' => 'ley-silla-05-plan',
                    'alt' => 'El plan de medidas y pausas',
                ],
            ],
            'features' => [
                [
                    'kicker' => 'El centro de un vistazo',
                    'title' => 'Qué te falta en cada centro',
                    'text' => 'Los requisitos de la ley y de las Disposiciones, con su avance por centro, para saber qué atender primero.',
                    'bullets' => [
                        'Asientos contra personas por turno, con aviso si faltan',
                        'La evaluación individual queda lista para el acta del recorrido',
                        'Un botón arma el texto sugerido para el reglamento interior',
                    ],
                    'shot' => 'ley-silla-02-center',
                    'alt' => 'Requisitos de Ley Silla de un centro con su avance',
                    'caption' => '7 de 13 requisitos. Lo pendiente, en orden.',
                ],
                [
                    'kicker' => 'Encuesta confidencial',
                    'title' => 'Lo que un inventario de sillas no ve',
                    'text' => 'Pregunta si hay asiento, si se puede usar sin consecuencias, si se respetan las pausas y si hay malestares, sin identificar a nadie.',
                    'bullets' => [
                        'Resultados solo como porcentajes por grupo',
                        'Un grupo con pocas personas no se muestra',
                        'Filtra por área para encontrar dónde actuar',
                    ],
                    'shot' => 'ley-silla-04-survey',
                    'alt' => 'Resultados de la encuesta confidencial por pregunta',
                    'caption' => 'Porcentajes por pregunta. Filtro por área.',
                ],
                [
                    'kicker' => 'Plan de acción',
                    'title' => 'Cada medida, con responsable y evidencia',
                    'text' => 'Pausas activas, pisos amortiguados o alternar tareas: cada medida lleva su estado, su responsable, su fecha y su evidencia.',
                    'bullets' => [
                        'Planeada, en curso o hecha',
                        'La evidencia queda adjunta a la medida',
                        'Nada se borra: el historial queda',
                    ],
                    'shot' => 'ley-silla-05-plan',
                    'alt' => 'Tablero del plan de medidas y pausas',
                    'caption' => 'Planeada · En curso · Hecha. Responsable y fecha de cada medida.',
                ],
            ],
            'beforeAfterTitle' => 'De la silla que nadie revisa al expediente del centro',
            'beforeAfter' => [
                [
                    'before' => 'Sillas compradas sin saber si alcanzan',
                    'after' => 'Asientos contra personas por turno, por centro',
                ],
                [
                    'before' => 'Sin saber si la gente puede sentarse de verdad',
                    'after' => 'Encuesta confidencial al personal',
                ],
                [
                    'before' => 'Un reglamento que no menciona los descansos',
                    'after' => 'Texto sugerido para revisar con tu abogado',
                ],
                [
                    'before' => 'Medidas que nadie da seguimiento',
                    'after' => 'Plan con responsable, fecha y evidencia',
                ],
            ],
            'audience' => [
                [
                    'who' => 'RH',
                    'title' => 'Cumple centro por centro',
                    'text' => 'Puestos, asientos, encuesta, reglamento y plan, con lo que falta a la vista.',
                ],
                [
                    'who' => 'Comisión de Seguridad e Higiene',
                    'title' => 'Evalúa con las siete condicionantes',
                    'text' => 'El nivel de riesgo de cada persona, listo para anexar al acta del recorrido.',
                ],
                [
                    'who' => 'Consultores',
                    'title' => 'El mismo método en cada cliente',
                    'text' => 'Cada empresa con sus datos aparte, desde tu cuenta.',
                ],
            ],
            'privacy' => [
                'title' => 'El personal contesta sin que nadie sepa quién fue',
                'items' => [
                    [
                        'title' => 'Encuesta confidencial',
                        'text' => 'No identifica a nadie: RH solo ve porcentajes por grupo.',
                    ],
                    [
                        'title' => 'Grupos con mínimo',
                        'text' => 'Un grupo con muy pocas personas no se muestra.',
                    ],
                    [
                        'title' => 'Evaluaciones, solo a quien debe',
                        'text' => 'Las que llevan nombre solo las ven el dueño, RH y la Comisión de su centro.',
                    ],
                    [
                        'title' => 'Historial completo',
                        'text' => 'Cada medida y su evidencia quedan; nada se borra.',
                    ],
                ],
            ],
            'faqs' => [
                [
                    'question' => '¿Desde cuándo es obligatoria la Ley Silla?',
                    'answer' => 'La reforma entró en vigor el 17 de junio de 2025. La STPS informó un plazo para que todas sus disposiciones fueran exigibles.',
                ],
                [
                    'question' => '¿A qué empresas aplica?',
                    'answer' => 'A los servicios, el comercio y los centros de trabajo análogos; en la industria, cuando lo permita la naturaleza del trabajo (LFT, art. 132, fr. V).',
                ],
                [
                    'question' => '¿Cuántas sillas debe tener la empresa?',
                    'answer' => 'Ni la ley ni las Disposiciones fijan una proporción: piden un número suficiente. Humetría compara asientos contra personas por turno como ayuda.',
                ],
                [
                    'question' => '¿Qué debe decir el reglamento interior?',
                    'answer' => 'Los periodos de reposo y las normas para usar los asientos (LFT, art. 423, fr. I y V).',
                ],
                [
                    'question' => '¿Cuántas pausas hay que dar?',
                    'answer' => 'Ni la ley ni las Disposiciones fijan número ni duración; se acuerdan con las personas trabajadoras.',
                ],
                [
                    'question' => '¿Cuál es la multa?',
                    'answer' => 'La ley no fija una multa propia para esta obligación. Revisa con tu abogado las sanciones generales de la LFT que aplicarían.',
                ],
            ],
            'related' => [
                [
                    'key' => 'nom_035',
                    'why' => 'La otra evaluación de la STPS por centro.',
                ],
                [
                    'key' => 'attendance',
                    'why' => 'Las pausas también se registran.',
                ],
                [
                    'key' => 'nom_037',
                    'why' => 'Condiciones de trabajo de quien teletrabaja.',
                ],
            ],
            'film' => [
                'name' => 'humetria-ley-silla',
                'title' => 'Ley Silla en Humetría',
                'description' => 'RH registra un puesto que se hace de pie y dónde va su asiento, la persona contesta la encuesta confidencial desde su celular mientras RH solo ve porcentajes por grupo, un botón arma el texto propuesto del reglamento interior y RH sube la evidencia de una medida del plan de acción.',
                'duration' => 30,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
