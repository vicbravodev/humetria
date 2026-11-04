<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\CostConcept;
use App\Modules\Compensation\Models\Benefit;

/** What a line of the cost cascade is called on screen: the salary, each benefit by its name, each employer line by its law. */
final class ConceptLabels
{
    private const array CONTRIBUTIONS = [
        'imss_fixed' => 'IMSS: cuota fija de enfermedad y maternidad',
        'imss_surplus' => 'IMSS: excedente de enfermedad y maternidad',
        'imss_pensioners' => 'IMSS: gastos médicos de pensionados',
        'imss_cash' => 'IMSS: prestaciones en dinero',
        'imss_disability_life' => 'IMSS: invalidez y vida',
        'work_risk' => 'IMSS: riesgo de trabajo',
        'imss_childcare' => 'IMSS: guarderías y prestaciones sociales',
        'retirement' => 'Retiro',
        'severance_old_age' => 'Cesantía en edad avanzada y vejez',
        'infonavit' => 'Infonavit',
        'payroll_tax' => 'Impuesto sobre nómina',
    ];

    /**
     * The name of each benefit of the company by the key the calculation knows it by (its base key, or its id).
     *
     * @return array<string, string>
     */
    public function benefitNames(): array
    {
        $names = [];

        foreach (Benefit::query()->get(['id', 'base_key', 'name']) as $benefit) {
            $names[$benefit->base_key ?? $benefit->id] = $benefit->name;
        }

        return $names;
    }

    /** @param  array<string, string>  $benefitNames  from benefitNames() */
    public function label(CostConcept $type, string $key, array $benefitNames): string
    {
        return match ($type) {
            CostConcept::Salary => 'Salario',
            CostConcept::Benefit => $benefitNames[$key] ?? BaseBenefit::tryFrom($key)?->label() ?? 'Prestación',
            CostConcept::Contribution => self::CONTRIBUTIONS[$key] ?? $key,
        };
    }
}
