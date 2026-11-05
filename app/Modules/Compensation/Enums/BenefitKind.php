<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/**
 * How a benefit is worked out. In every kind the value is stored as a whole number of hundredths (two decimals):
 *
 *  - salary_days: days of salary; 15 days is 1500.
 *  - salary_percent: percent of the salary; 25 % is 2500.
 *  - fixed_amount: pesos per period, in cents; $500.00 is 50000.
 *  - uma_multiple: times the daily UMA; 1.5 UMA is 150.
 */
enum BenefitKind: string
{
    case SalaryDays = 'salary_days';
    case SalaryPercent = 'salary_percent';
    case FixedAmount = 'fixed_amount';
    case UmaMultiple = 'uma_multiple';

    public function label(): string
    {
        return match ($this) {
            self::SalaryDays => 'Días de salario',
            self::SalaryPercent => 'Porcentaje del salario',
            self::FixedAmount => 'Monto fijo',
            self::UmaMultiple => 'Múltiplo de la UMA',
        };
    }

    /** What the number is of, as a person reads it next to the value ("días", "% del salario", "pesos", "UMA"). */
    public function unit(): string
    {
        return match ($this) {
            self::SalaryDays => 'días de salario',
            self::SalaryPercent => '% del salario',
            self::FixedAmount => 'pesos',
            self::UmaMultiple => 'UMA',
        };
    }
}
