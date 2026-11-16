<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Demos\Contracts\DemoDoorPool;

/** The door's people as the roster marks them (reservedForDoor), in the roster's order. */
final class RosterDoorPool implements DemoDoorPool
{
    public function employeeIds(): array
    {
        $reserved = array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->reservedForDoor);

        return array_values(array_map(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key), $reserved));
    }
}
