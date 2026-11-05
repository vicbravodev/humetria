<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/** What a line of the cost cascade is: the salary, a benefit, or an employer contribution. */
enum CostConcept: string
{
    case Salary = 'salary';
    case Benefit = 'benefit';
    case Contribution = 'contribution';

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Salario',
            self::Benefit => 'Prestación',
            self::Contribution => 'Cuota o aportación patronal',
        };
    }
}
