<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The page of the compensation calculator (/compensacion). What it says of the law and its rates comes from
 * docs/normas/compensation/criterios.md.
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
            'h1' => 'Revisión salarial y de contrato colectivo, con el costo real de cada propuesta',
            'lead' => 'Elige un aumento y ve lo que cuesta de verdad: salario, prestaciones, cuotas del IMSS, Infonavit e impuesto sobre nómina. Compara la propuesta de la empresa con la del sindicato, proyecta los años siguientes y lleva a la mesa un reporte de solo lectura.',
            'norm' => [
                'title' => 'Qué toma en cuenta el cálculo',
                'lead' => 'Las tasas salen de la ley y de fuentes oficiales, cada una con su vigencia. Con la UMA y el salario mínimo de 2026:',
                'rules' => [
                    ['text' => 'El salario base de cotización integra el aguinaldo y la prima vacacional, con piso en el salario mínimo y tope de 25 UMA.', 'source' => 'Ley del Seguro Social, arts. 27 y 28'],
                    ['text' => 'La cuota patronal de cesantía en edad avanzada y vejez sube cada 1 de enero según el rango de salario, de 2023 a 2030.', 'source' => 'Ley del Seguro Social, art. 168, fracción II; decreto DOF 16/12/2020, transitorio segundo'],
                    ['text' => 'El patrón aporta al Infonavit el 5 % del salario, con el mismo tope de 25 UMA.', 'source' => 'Ley del Infonavit, art. 29, fracción II'],
                    ['text' => 'Mínimos de ley: 15 días de aguinaldo, 25 % de prima vacacional y vacaciones desde 12 días al año.', 'source' => 'Ley Federal del Trabajo, arts. 76, 80 y 87'],
                    ['text' => 'El impuesto sobre nómina es estatal: cada estado fija su tasa (3 % en la mayoría en 2026) y la que aplica es la del estado donde se trabaja.', 'source' => 'Ley de Hacienda, Código Fiscal o Financiero de cada estado'],
                ],
                'facts' => [
                    ['label' => 'UMA diaria 2026', 'value' => '$117.31'],
                    ['label' => 'Salario mínimo general 2026', 'value' => '$315.04 diarios'],
                    ['label' => 'Tope del salario base de cotización', 'value' => '25 UMA ($2,932.75 diarios en 2026)'],
                ],
            ],
            'features' => [
                ['title' => 'El costo de un aumento, completo', 'text' => 'Costea un aumento con salario, prestaciones, cuotas del IMSS, Infonavit e impuesto sobre nómina del estado, por persona o por nivel de puesto con tu tabulador.'],
                ['title' => 'Compara escenarios', 'text' => 'Pon lado a lado la propuesta de la empresa y la del sindicato, con la distancia entre ellas, para sindicalizados y personal de confianza.'],
                ['title' => 'Proyecta los años siguientes', 'text' => 'Ve el costo del primer año y cómo crece en los siguientes, con los supuestos de inflación, revisiones y plantilla declarados en el reporte.'],
                ['title' => 'Un reporte para la mesa', 'text' => 'Comparte el reporte de un escenario con un enlace de solo lectura que dura de 1 a 7 días y que puedes revocar antes.'],
            ],
            'audience' => [
                'company' => 'La empresa costea su revisión salarial y contractual con su plantilla, su tabulador y sus prestaciones. El sindicato puede tener su propia cuenta, con sus datos aparte.',
                'consultant' => 'Si asesoras a varias empresas en sus revisiones, llevas los escenarios de cada una por separado desde la misma cuenta.',
            ],
            'privacy' => 'La empresa y el sindicato trabajan cada uno con sus datos aparte, y el enlace del reporte es de solo lectura y vence solo.',
            'faqs' => [
                ['question' => '¿Cuánto le cuesta a la empresa un aumento salarial?', 'answer' => 'Más que el aumento mismo: sube el salario base de cotización y, con él, las cuotas del IMSS, el Infonavit y el impuesto sobre nómina. La calculadora suma cada concepto con las tasas vigentes y su fuente.'],
                ['question' => '¿Qué cuotas patronales incluye el cálculo?', 'answer' => 'Las del IMSS (enfermedad y maternidad, invalidez y vida, guarderías, retiro, cesantía y vejez), el 5 % del Infonavit y el impuesto sobre nómina del estado. La prima de riesgo de trabajo la fija el IMSS por empresa, así que la capturas tú.'],
                ['question' => '¿Toma en cuenta el aumento de la cuota de cesantía y vejez?', 'answer' => 'Sí. Aplica la tasa de cada año y rango de salario que fija el decreto publicado en el DOF el 16 de diciembre de 2020, que sube cada 1 de enero hasta 2030.'],
                ['question' => '¿Qué impuesto sobre nómina usa?', 'answer' => 'El del estado donde se trabaja, con la tasa de su ley vigente. En 2026 la mayoría de los estados cobra 3 %; Ciudad de México y Chihuahua, 4 %, y Aguascalientes, 2.5 %. Sinaloa tiene una tarifa progresiva que la calculadora marca como no calculada.'],
                ['question' => '¿El sindicato puede usar la calculadora?', 'answer' => 'Sí. El sindicato tiene su propia cuenta, con sus datos aparte de los de la empresa, y arma sus escenarios para la revisión.'],
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
