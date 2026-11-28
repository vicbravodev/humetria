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
        'imss_fixed' => 'IMSS: cuota fija de enfermedad y maternidad', // @pest-mutate-ignore: RemoveArrayItem
        'imss_surplus' => 'IMSS: excedente de enfermedad y maternidad', // @pest-mutate-ignore: RemoveArrayItem
        'imss_pensioners' => 'IMSS: gastos médicos de pensionados', // @pest-mutate-ignore: RemoveArrayItem
        'imss_cash' => 'IMSS: prestaciones en dinero', // @pest-mutate-ignore: RemoveArrayItem
        'imss_disability_life' => 'IMSS: invalidez y vida', // @pest-mutate-ignore: RemoveArrayItem
        'work_risk' => 'IMSS: riesgo de trabajo', // @pest-mutate-ignore: RemoveArrayItem
        'imss_childcare' => 'IMSS: guarderías y prestaciones sociales', // @pest-mutate-ignore: RemoveArrayItem
        'retirement' => 'Retiro', // @pest-mutate-ignore: RemoveArrayItem
        'severance_old_age' => 'Cesantía en edad avanzada y vejez', // @pest-mutate-ignore: RemoveArrayItem
        'infonavit' => 'Infonavit', // @pest-mutate-ignore: RemoveArrayItem
        'payroll_tax' => 'Impuesto sobre nómina', // @pest-mutate-ignore: RemoveArrayItem
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

    /** What a fiscal value is called when the report says it is out of date: the state rates of the payroll tax are one. */
    public function fiscalLabel(string $key): string
    {
        $fixed = ['uma' => 'UMA', 'minimum_wage' => 'Salario mínimo', 'minimum_wage_border' => 'Salario mínimo de la frontera norte'];

        if (isset($fixed[$key])) {
            return $fixed[$key];
        }

        if (str_starts_with($key, 'payroll_tax')) {
            return 'Impuesto sobre nómina de los estados';
        }

        if (str_starts_with($key, 'severance_old_age')) {
            return 'Cesantía en edad avanzada y vejez';
        }

        return $key;
    }
}
