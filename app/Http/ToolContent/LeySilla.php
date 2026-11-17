<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The page of the Ley Silla (/ley-silla). What it says of the law comes from docs/normas/ley-silla/criterios.md and
 * docs/normas/ley-silla/texto-de-referencia.md.
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
            'h1' => 'Ley Silla: asientos, pausas y reglamento interior, con la evidencia a la mano',
            'lead' => 'Registras qué puestos se hacen de pie y dónde va su asiento, preguntas al personal de forma confidencial y Humetría te sugiere qué escribir en tu reglamento interior. Cada medida del plan de acción guarda su responsable, su fecha y su evidencia.',
            'norm' => [
                'title' => 'Qué pide la ley',
                'lead' => 'La reforma a la Ley Federal del Trabajo publicada en el DOF el 19 de diciembre de 2024 y las Disposiciones de la STPS del 17 de julio de 2025 obligan a dar asiento y descanso a quien trabaja de pie.',
                'rules' => [
                    ['text' => 'Proveer suficientes asientos o sillas con respaldo, a disposición de todas las personas, para trabajar o para descansar periódicamente durante la jornada. En la industria, cuando lo permita la naturaleza del trabajo.', 'source' => 'LFT, art. 132, fracción V'],
                    ['text' => 'Queda prohibido obligar a una persona a estar de pie toda la jornada e impedirle tomar asiento periódicamente.', 'source' => 'LFT, art. 133, fracción XVII Bis'],
                    ['text' => 'Evaluar el riesgo de cada persona que trabaja de pie con siete condicionantes. La evaluación la hace un integrante de la Comisión de Seguridad e Higiene y se asienta en sus actas.', 'source' => 'Disposiciones de la STPS, Quinta B, C y D'],
                    ['text' => 'El reglamento interior de trabajo debe incluir los periodos de reposo durante la jornada y las normas para usar los asientos.', 'source' => 'LFT, art. 423, fracciones I y V'],
                    ['text' => 'Informar a las personas de los riesgos y las medidas, señalizar las áreas de asientos y canalizar a atención médica cuando haya signos o síntomas.', 'source' => 'Disposiciones de la STPS, Quinta H, I y J'],
                ],
                'facts' => [
                    ['label' => 'Exigible desde', 'value' => '15 de diciembre de 2025 (STPS, boletín 59/25)'],
                    ['label' => 'A quién aplica', 'value' => 'Servicios, comercio y análogos; la industria, cuando el trabajo lo permite'],
                    ['label' => 'Multas aplicables', 'value' => 'De 50 a 5,000 UMA por persona afectada (arts. 992, 994 V y 1002)'],
                ],
            ],
            'features' => [
                ['title' => 'Puestos de pie y sus asientos', 'text' => 'Registras cada puesto que se hace de pie y el flujograma de la Figura 1 indica dónde va su asiento. Humetría compara los asientos contra las personas por turno y avisa si faltan; esa cuenta es una ayuda, no un estándar de la STPS.'],
                ['title' => 'Encuesta confidencial al personal', 'text' => 'Pregunta si hay asiento, si se puede usar sin consecuencias, si se respetan las pausas y si hay malestares. Detecta lo que un inventario de sillas no ve.'],
                ['title' => 'Evaluación individual de riesgo', 'text' => 'La Comisión evalúa a cada persona con las siete condicionantes y obtiene su nivel: Bajo, Medio o Alto. El registro queda listo para anexarse al acta del recorrido.'],
                ['title' => 'Texto sugerido para el reglamento interior', 'text' => 'Un botón arma un borrador con los riesgos de estar de pie, las pausas y la ubicación de los asientos de tu centro. Lo revisa tu abogado y lo aprueba la comisión mixta.'],
                ['title' => 'Plan de acción con evidencia', 'text' => 'Cada medida, como pausas activas, pisos amortiguados o alternar tareas, lleva estado, responsable, fecha y evidencia. Nada se borra.'],
            ],
            'audience' => [
                'company' => 'RH registra puestos y asientos, la Comisión de Seguridad e Higiene evalúa a cada persona y el personal contesta la encuesta desde su celular.',
                'consultant' => 'Si asesoras a varias empresas, llevas la Ley Silla de cada una por separado desde la misma cuenta, con su propio expediente.',
            ],
            'privacy' => 'La encuesta al personal es confidencial: no identifica a nadie y RH solo ve porcentajes por grupo, con un mínimo de personas por grupo. Las evaluaciones con nombre solo las ven el dueño, RH y la Comisión de su centro.',
            'faqs' => [
                ['question' => '¿Desde cuándo es obligatoria la Ley Silla?', 'answer' => 'La reforma a la Ley Federal del Trabajo entró en vigor el 17 de junio de 2025, y la STPS informó que desde el 15 de diciembre de 2025 todas sus disposiciones son exigibles (boletín 59/25).'],
                ['question' => '¿A qué empresas aplica la Ley Silla?', 'answer' => 'A los servicios, el comercio y los centros de trabajo análogos; en los establecimientos industriales, cuando lo permita la naturaleza del trabajo (LFT, art. 132, fracción V). Las Disposiciones de la STPS aplican a todo centro con personas que trabajan de pie.'],
                ['question' => '¿Cuántas sillas debe tener la empresa?', 'answer' => 'Ni la ley ni las Disposiciones fijan una proporción: piden un número suficiente, a disposición de todas las personas. Humetría compara los asientos contra las personas por turno como ayuda, no como estándar de la autoridad.'],
                ['question' => '¿Qué debe decir el reglamento interior por la Ley Silla?', 'answer' => 'Los periodos de reposo y las normas para usar los asientos (LFT, art. 423, fracciones I y V). La STPS precisó que debe incluir los factores de riesgo de estar de pie, las pausas y la ubicación de los asientos. Lo formula una comisión mixta y se deposita ante el Centro Federal de Conciliación y Registro Laboral.'],
                ['question' => '¿Cuántas pausas hay que dar?', 'answer' => 'Ni la ley ni las Disposiciones fijan número ni duración. Cuando el asiento no está en el propio puesto, la STPS indica acordar con las personas trabajadoras el número y la duración de las pausas.'],
                ['question' => '¿Cuál es la multa por no cumplir la Ley Silla?', 'answer' => 'La ley no fija una multa propia para esta obligación. Las aplicables van de 50 a 5,000 veces la UMA por cada persona afectada, y se duplican si hay reincidencia (LFT, arts. 992, 994, fracción V, y 1002).'],
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
