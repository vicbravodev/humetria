<?php

declare(strict_types=1);

namespace App\Modules\Leave\Contracts;

use App\Modules\Leave\Data\AbsenceData;
use Carbon\CarbonImmutable;

/**
 * Who of the current company is away, by their approved vacations and leaves, for the modules that need to know it
 * (attendance, the org chart). It says vacations or leave, never the type of the leave (criterio C-22).
 */
interface Absences
{
    /**
     * The people, among those asked, who are away that day. One query for any number of people.
     *
     * @param  list<string>  $employeeIds
     * @return list<string> employee ids, once each
     */
    public function absentOn(CarbonImmutable $day, array $employeeIds): array;

    /**
     * The absences of the people asked that touch the range, both ends included, each with its whole stretch even when
     * the range cuts it, from the earliest. One query for any number of people.
     *
     * @param  list<string>  $employeeIds
     * @return list<AbsenceData>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to, array $employeeIds): array;
}
