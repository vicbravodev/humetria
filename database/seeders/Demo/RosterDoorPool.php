<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Organization\Contracts\Roster;

/**
 * The door's people as the roster marks them (reservedForDoor), in the roster's order, found in the company entered by
 * their employee number: their ids are new on every prepare.
 */
final readonly class RosterDoorPool implements DemoDoorPool
{
    public function __construct(private Roster $roster) {}

    public function employeeIds(): array
    {
        $numbers = array_values(array_map(
            fn (DemoPerson $person): string => $person->employeeNumber,
            array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->reservedForDoor),
        ));
        $ids = $this->roster->idsByEmployeeNumber($numbers);

        return array_values(array_filter(array_map(fn (string $number): ?string => $ids[$number] ?? null, $numbers), is_string(...)));
    }
}
