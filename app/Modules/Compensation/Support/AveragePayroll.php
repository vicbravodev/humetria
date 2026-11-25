<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Organization\Data\HeadcountCell;

/**
 * The payroll of a scenario of the `average` source as the calculation reads it: one headcount cell under a category and level
 * that is not one of the company's, and the daily salary of a monthly pay (divided by 30, LSS art. 29).
 */
final class AveragePayroll
{
    /** The id of the one category and level of an average payroll; it is not a uuid and no table has it. */
    public const string GRADE_ID = 'average';

    public const string LABEL = 'Plantilla promedio';

    public static function cell(Scenario $scenario): HeadcountCell
    {
        // The first day of the month of hire, a whole number of years before the effective date: the average worker keeps the
        // same years of service, and so the same days of vacation, for the twelve months costed.
        $hired = $scenario->effective_on->subYears($scenario->average_tenure_years ?? 0)->startOfMonth();

        return HeadcountCell::assumed(self::GRADE_ID, $scenario->default_state, $hired, self::workers($scenario), $scenario->average_group === PayGroup::Trusted);
    }

    /** @return int<1, max> */
    public static function workers(Scenario $scenario): int
    {
        return max(1, $scenario->average_workers ?? 1);
    }

    public static function dailySalaryCents(Scenario $scenario): int
    {
        return Rate::fraction($scenario->average_monthly_salary_cents ?? 0, 30)->of(1);
    }
}
