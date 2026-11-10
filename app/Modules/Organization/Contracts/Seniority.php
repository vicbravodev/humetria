<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

use Carbon\CarbonImmutable;

/**
 * Since when each person counts their seniority in the current company.
 */
interface Seniority
{
    /**
     * The seniority the company recognizes and, without one, the hire date. People who left are included (what they
     * are owed on leaving is counted from it); whoever has neither date, or is of another company, is left out.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, CarbonImmutable> by employee id
     */
    public function startDatesFor(array $employeeIds): array;
}
