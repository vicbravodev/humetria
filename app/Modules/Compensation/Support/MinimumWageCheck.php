<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use Carbon\CarbonImmutable;

/**
 * Whether a daily salary is under the minimum wage in force on the day it starts to count (not today's): the warning
 * of {@see SalaryRules::salaryWarning()}, with the parameters of that date.
 */
final readonly class MinimumWageCheck
{
    public function __construct(private FiscalParameters $fiscal) {}

    public function warning(int $cents, CarbonImmutable $on): ?string
    {
        $legal = $this->fiscal->at($on);

        return SalaryRules::salaryWarning($cents, $legal->minimumWage(), $legal->minimumWageBorder());
    }
}
