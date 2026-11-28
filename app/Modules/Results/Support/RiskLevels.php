<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Instruments\Data\RiskLevel;

/**
 * The five risk levels with the action each one needs, word for word from Tablas 4 and 7 of the norm (II.4, III.4;
 * criterio C-15), from Nulo to Muy alto.
 */
final class RiskLevels
{
    private const array ACTIONS = [
        'null' => 'El riesgo resulta despreciable por lo que no se requiere medidas adicionales.', // @pest-mutate-ignore: RemoveArrayItem
        'low' => 'Es necesario una mayor difusión de la política de prevención de riesgos psicosociales y programas para: la prevención de los factores de riesgo psicosocial, la promoción de un entorno organizacional favorable y la prevención de la violencia laboral.', // @pest-mutate-ignore: RemoveArrayItem
        'medium' => 'Se requiere revisar la política de prevención de riesgos psicosociales y programas para la prevención de los factores de riesgo psicosocial, la promoción de un entorno organizacional favorable y la prevención de la violencia laboral, así como reforzar su aplicación y difusión, mediante un Programa de intervención.', // @pest-mutate-ignore: RemoveArrayItem
        'high' => 'Se requiere realizar un análisis de cada categoría y dominio, de manera que se puedan determinar las acciones de intervención apropiadas a través de un Programa de intervención, que podrá incluir una evaluación específica y deberá incluir una campaña de sensibilización, revisar la política de prevención de riesgos psicosociales y programas para la prevención de los factores de riesgo psicosocial, la promoción de un entorno organizacional favorable y la prevención de la violencia laboral, así como reforzar su aplicación y difusión.', // @pest-mutate-ignore: RemoveArrayItem
        'very_high' => 'Se requiere realizar el análisis de cada categoría y dominio para establecer las acciones de intervención apropiadas, mediante un Programa de intervención que deberá incluir evaluaciones específicas, y contemplar campañas de sensibilización, revisar la política de prevención de riesgos psicosociales y programas para la prevención de los factores de riesgo psicosocial, la promoción de un entorno organizacional favorable y la prevención de la violencia laboral, así como reforzar su aplicación y difusión.', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /**
     * @return list<array{value: string, label: string, action: string}>
     */
    public static function catalog(): array
    {
        return array_map(fn (RiskLevel $level): array => ['value' => $level->value, 'label' => $level->label(), 'action' => self::ACTIONS[$level->value]], RiskLevel::cases());
    }
}
