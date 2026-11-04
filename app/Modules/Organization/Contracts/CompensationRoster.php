<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

use App\Modules\Organization\Data\CompensationPerson;
use App\Modules\Organization\Data\HeadcountCell;
use App\Modules\Organization\Data\RosterGaps;
use Carbon\CarbonImmutable;

/**
 * The roster as the contract calculator reads it, in the current account (company or union): counts and employee
 * numbers, never a name, a contact, a gender or the internal id. Only active people with a category and level, a hire
 * date and a staff group can be costed.
 */
interface CompensationRoster
{
    /**
     * The people hired on or before `$asOf`, counted by category and level, area, group, state and month of hire, in
     * one query and without loading anyone. Whoever has an employee number in `$excludingNumbers` (compared ignoring
     * case) is left out: they are costed one by one through `individuals()`.
     *
     * @param  list<string>  $excludingNumbers
     * @return list<HeadcountCell>
     */
    public function cells(CarbonImmutable $asOf, array $excludingNumbers): array;

    /**
     * Those employee numbers' people, found ignoring case and keyed by the number as the account stores it. A number
     * that is unknown, deactivated or whose person cannot be costed is left out.
     *
     * @param  list<string>  $employeeNumbers
     * @return array<string, CompensationPerson>
     */
    public function individuals(array $employeeNumbers): array;

    public function gaps(): RosterGaps;
}
