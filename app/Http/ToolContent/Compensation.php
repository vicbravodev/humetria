<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The public page of Compensación (/compensacion). What it says of the law comes from docs/normas/compensation/criterios.md; its
 * screenshots are the demo company's, in public/images/tools.
 *
 * @phpstan-import-type Page from ToolPages
 */
final class Compensation
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'Costo de un aumento salarial y revisión del contrato',
            'description' => 'Calcula cuánto cuesta un aumento con prestaciones, IMSS, Infonavit e impuesto sobre nómina, y compara propuestas en la revisión del contrato colectivo.',
            'name' => 'Compensación',
            'family' => 'Personas y sueldos',
            'chip' => 'Revisión salarial y de contrato colectivo',
            'h1' => 'Sabe cuánto cuesta un aumento antes de sentarte a negociarlo',
            'lead' => 'Elige un aumento y ve lo que cuesta de verdad: salario, prestaciones, cuotas del IMSS, Infonavit e impuesto sobre nómina. Compara la propuesta de la empresa con la del sindicato, proyecta los años siguientes y lleva a la mesa un reporte de solo lectura.',
            'proof' => 'El costo completo y no solo el porcentaje, cada tasa con su fuente y su vigencia, y la empresa y el sindicato cada uno con sus datos.',
            'norm' => [
                'title' => 'Qué toma en cuenta el cálculo',
                'lead' => 'Un aumento sube el salario base de cotización y, con él, todo lo que se paga sobre él. Cada tasa sale de la ley, con su vigencia.',
                'facts' => [
                    [
                        'label' => 'Tope del salario base',
                        'value' => '25 UMA',
                        'note' => 'LSS, art. 28',
                    ],
                    [
                        'label' => 'Infonavit',
                        'value' => '5 % del salario',
                        'note' => 'Ley del Infonavit, art. 29',
                    ],
                    [
                        'label' => 'Cesantía y vejez',
                        'value' => 'Sube cada 1 de enero',
                        'note' => 'Hasta 2030 · decreto DOF 16-12-2020',
                    ],
                ],
                'rules' => [
                    [
                        'text' => 'El salario base de cotización integra el aguinaldo y la prima vacacional, con piso en el salario mínimo y tope de 25 UMA.',
                        'source' => 'Ley del Seguro Social, arts. 27 y 28',
                    ],
                    [
                        'text' => 'La cuota patronal de cesantía en edad avanzada y vejez sube cada año según el rango de salario, de 2023 a 2030.',
                        'source' => 'LSS, art. 168, fr. II; DOF 16-12-2020',
                    ],
                    [
                        'text' => 'Mínimos de ley: 15 días de aguinaldo, 25 % de prima vacacional y vacaciones desde 12 días al año.',
                        'source' => 'LFT, arts. 76, 80 y 87',
                    ],
                    [
                        'text' => 'El impuesto sobre nómina es estatal: aplica la tasa del estado donde se trabaja.',
                        'source' => 'Ley de hacienda de cada estado',
                    ],
                ],
                'note' => 'Las cifras de UMA y salario mínimo se actualizan cada año en la calculadora.',
            ],
            'guideLink' => false,
            'pick' => null,
            'steps' => [
                [
                    'title' => 'Cargas tu contrato',
                    'text' => 'Salarios por nivel, prestaciones y plantilla, con el estado donde se trabaja.',
                    'shot' => 'compensation-06-contract',
                    'alt' => 'El tabulador del contrato por categoría y nivel',
                ],
                [
                    'title' => 'Armas escenarios',
                    'text' => 'Un aumento por persona o por nivel, para sindicalizados y personal de confianza.',
                    'shot' => 'compensation-02-scenario',
                    'alt' => 'El costo de un escenario en vivo',
                ],
                [
                    'title' => 'Llevas el reporte a la mesa',
                    'text' => 'Comparas propuestas, proyectas los años siguientes y compartes un enlace de solo lectura.',
                    'shot' => 'compensation-04-report',
                    'alt' => 'El reporte del escenario para la mesa',
                ],
            ],
            'features' => [
                [
                    'kicker' => 'Costeo',
                    'title' => 'El costo de un aumento, completo y en vivo',
                    'text' => 'Mueve el porcentaje y ve el costo del primer año con salario, prestaciones, IMSS, Infonavit e impuesto sobre nómina.',
                    'bullets' => [
                        'Por persona o por nivel de puesto, con tu tabulador',
                        'Con las tasas del año y del estado',
                        'Cada supuesto queda declarado',
                    ],
                    'shot' => 'compensation-02-scenario',
                    'alt' => 'Editor de escenario con el costo en vivo',
                    'caption' => 'Costo del primer año, en vivo. Cada concepto por separado.',
                ],
                [
                    'kicker' => 'Comparación',
                    'title' => 'La distancia entre la empresa y el sindicato',
                    'text' => 'Pon lado a lado la contraoferta de la empresa y la propuesta del sindicato, y ve qué mueve la diferencia.',
                    'bullets' => [
                        'La brecha en pesos del primer año',
                        'Qué conceptos explican la diferencia',
                        'Para sindicalizados y personal de confianza',
                    ],
                    'shot' => 'compensation-03-compare',
                    'alt' => 'Comparación de dos propuestas con su diferencia',
                    'caption' => 'La brecha entre propuestas. Qué la mueve.',
                ],
                [
                    'kicker' => 'Proyección',
                    'title' => 'Lo que cuesta este año y los siguientes',
                    'text' => 'El costo crece con la inflación, las revisiones y la plantilla: la proyección lo muestra con sus supuestos.',
                    'bullets' => [
                        'Supuestos declarados en el reporte',
                        'Comparación contra la otra propuesta',
                        'Un enlace de solo lectura que vence solo',
                    ],
                    'shot' => 'compensation-05-projection',
                    'alt' => 'Proyección de los próximos años con sus supuestos',
                    'caption' => 'Próximos años. Supuestos a la vista.',
                ],
            ],
            'beforeAfterTitle' => 'De la hoja de cálculo que nadie entiende al reporte para la mesa',
            'beforeAfter' => [
                [
                    'before' => 'Un Excel con fórmulas que solo entiende quien lo hizo',
                    'after' => 'El costo completo, con cada concepto separado',
                ],
                [
                    'before' => 'Negociar con el porcentaje, sin saber el costo real',
                    'after' => 'El costo en pesos de cada propuesta',
                ],
                [
                    'before' => 'Tasas del IMSS desactualizadas',
                    'after' => 'Tasas del año, con su fuente',
                ],
                [
                    'before' => 'Mandar el archivo por correo a la mesa',
                    'after' => 'Un enlace de solo lectura que vence solo',
                ],
            ],
            'audience' => [
                [
                    'who' => 'RH y finanzas',
                    'title' => 'Costean antes de negociar',
                    'text' => 'Escenarios, comparación y proyección con los datos de la empresa.',
                ],
                [
                    'who' => 'Sindicatos',
                    'title' => 'Su propia cuenta, sus propios datos',
                    'text' => 'Arman su propuesta aparte de la empresa, con la misma calculadora.',
                ],
                [
                    'who' => 'Consultores',
                    'title' => 'Asesoran la revisión de cada cliente',
                    'text' => 'Cada empresa o sindicato con sus datos aparte.',
                ],
            ],
            'privacy' => null,
            'faqs' => [
                [
                    'question' => '¿Cuánto le cuesta a la empresa un aumento salarial?',
                    'answer' => 'Más que el aumento mismo: sube el salario base de cotización y, con él, las cuotas del IMSS, el Infonavit y el impuesto sobre nómina.',
                ],
                [
                    'question' => '¿Qué cuotas patronales incluye el cálculo?',
                    'answer' => 'Las del IMSS, el 5 % del Infonavit y el impuesto sobre nómina del estado.',
                ],
                [
                    'question' => '¿Toma en cuenta el aumento de cesantía y vejez?',
                    'answer' => 'Sí. Aplica la tasa de cada año y rango de salario del decreto del 16 de diciembre de 2020.',
                ],
                [
                    'question' => '¿Qué impuesto sobre nómina usa?',
                    'answer' => 'El del estado donde se trabaja, con la tasa de su ley vigente.',
                ],
                [
                    'question' => '¿El sindicato puede usar la calculadora?',
                    'answer' => 'Sí. Tiene su propia cuenta, con sus datos aparte de los de la empresa.',
                ],
            ],
            'related' => [
                [
                    'key' => 'leave',
                    'why' => 'La prima vacacional entra en el costo.',
                ],
                [
                    'key' => 'org_chart',
                    'why' => 'Los niveles de puesto de tu tabulador.',
                ],
                [
                    'key' => 'attendance',
                    'why' => 'Las horas extra que también cuestan.',
                ],
            ],
            'film' => [
                'name' => 'humetria-compensation',
                'title' => 'Compensación en Humetría',
                'description' => 'Se elige un aumento del 6 % y se costea con salario, prestaciones, IMSS, Infonavit e impuesto sobre nómina, se compara la propuesta de la empresa con la del sindicato, se proyecta el costo a cinco años y se comparte el reporte con un enlace de solo lectura, todo con cifras de ejemplo.',
                'duration' => 31,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
