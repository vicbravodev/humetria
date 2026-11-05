<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Support\Calculation\Rate;

/**
 * A benefit as the calculation reads it: its attributes and its value for the period being costed, with no model behind it.
 *
 * `key` is stable: the three the law ties to the integration factor are `christmas_bonus` (days of aguinaldo), `vacation`
 * (days) and `vacation_premium` (a fraction: 25 % is 0.25); the vouchers are `food_vouchers`.
 *
 * `amount` by kind: days of salary (15), a fraction of the salary (0.08), cents per period (50000 is $500.00) or times the
 * daily UMA (1.5). A row of `tenureTable` replaces it from its year of service on, in the same unit.
 *
 * `sbcCapCents` is cents per calendar day: how much of the benefit integrates at most (up_to_cap). `capCents` is the most
 * one person costs in a year.
 */
final readonly class BenefitSpec
{
    /**
     * @param  list<string>  $jobGradeIds  empty = every category and level
     * @param  list<array{fromYears: int, value: Rate}>  $tenureTable  ascending by year, may be empty
     */
    public function __construct(
        public string $key,
        public BenefitKind $kind,
        public Rate $amount,
        public Frequency $frequency,
        public ?PayGroup $group,
        public array $jobGradeIds,
        public int $minTenureMonths,
        public array $tenureTable,
        public SbcIntegration $sbcIntegration,
        public ?int $sbcCapCents,
        public bool $inPayrollTax,
        public bool $incomeTaxExempt,
        public bool $inSeniorityBase,
        public ?int $capCents,
    ) {}
}
