<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Timezones;

/**
 * The zone a person's days and weeks are read in: their work center's (design of time zones, §3); the company's for
 * someone who is not one of its people.
 */
final readonly class PersonTimezone
{
    public function __construct(private Roster $roster, private Timezones $timezones) {}

    public function of(string $employeeId): string
    {
        $person = $this->roster->find($employeeId);

        return $person === null ? $this->timezones->forTenant() : $this->timezones->forCenter($person->workCenterId);
    }
}
