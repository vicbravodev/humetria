<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\FiscalSnapshot;
use Carbon\CarbonImmutable;
use OutOfBoundsException;

/**
 * Whether a daily salary is under the minimum wage in force on the day it starts to count (not today's): the warning
 * of {@see SalaryRules::salaryWarning()}, with the parameters of that date.
 */
final class MinimumWageCheck
{
    /** @var array<string, FiscalSnapshot> the parameters already read, by day: a page of salaries shares a few dates */
    private array $snapshots = [];

    public function __construct(private readonly FiscalParameters $fiscal) {}

    /** The salary in force today has to reach today's minimum, whenever it started: the one of its own day only when that is later. */
    public function inForce(int $cents, CarbonImmutable $from, CarbonImmutable $today): ?string
    {
        return $this->warning($cents, $from->greaterThan($today) ? $from : $today);
    }

    public function warning(int $cents, CarbonImmutable $on): ?string
    {
        $legal = $this->snapshots[$on->toDateString()] ??= $this->fiscal->at($on);

        try {
            return SalaryRules::salaryWarning($cents, $legal->minimumWage(), $legal->minimumWageBorder());
        } catch (OutOfBoundsException) {
            // A date the platform has no minimum for (before its first row): nothing to compare with, so nothing is said.
            return null;
        }
    }
}
