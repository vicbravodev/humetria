<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/** One person of the demo company, as the roster writes them: every story reads the same people. */
final readonly class DemoPerson
{
    /**
     * @param  string  $key  stable across runs: the employee's id is derived from it (DemoRoster::employeeId).
     * @param  'female'|'male'  $gender
     * @param  string  $centerKey  a key of DemoRoster::CENTERS.
     * @param  string  $category  with the level, the person's job grade.
     * @param  int  $tenureDays  how long before the day the demo is prepared they joined.
     * @param  string|null  $reportsTo  their manager's key; null only for the director.
     * @param  bool  $reservedForDoor  rank-and-file people whose invitations stay pending, for prospects to answer live.
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $email,
        public string $gender,
        public string $centerKey,
        public string $area,
        public string $position,
        public string $category,
        public string $level,
        public int $tenureDays,
        public ?string $reportsTo,
        public bool $reservedForDoor,
        public string $employeeNumber,
    ) {}
}
