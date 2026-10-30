<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

use App\Modules\Organization\Data\RosterEntry;

/**
 * The people of the current company, as other modules read them.
 */
interface Roster
{
    /**
     * The active people of a work center, by name.
     *
     * @return list<RosterEntry>
     */
    public function activeIn(string $workCenterId): array;

    /** A person by id, deactivated ones included; null outside the company. */
    public function find(string $employeeId): ?RosterEntry;

    /**
     * People by id, deactivated ones included; ids outside the company are left out.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, RosterEntry> by id
     */
    public function findMany(array $employeeIds): array;
}
