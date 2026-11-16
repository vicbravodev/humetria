<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\User;
use App\Modules\Tenancy\Contracts\WorkCenters;
use InvalidArgumentException;

/** What every story works with: the demo company, already entered, and the panel members who act in it. */
final readonly class DemoContext
{
    public function __construct(
        public string $tenantId,
        public User $owner,
        public User $hr,
    ) {}

    /**
     * The id of a work center by its roster key.
     *
     * @throws InvalidArgumentException when the company has no such center (yet).
     */
    public function center(string $key): string
    {
        $name = DemoRoster::CENTERS[$key] ?? throw new InvalidArgumentException("[{$key}] is not a demo work center.");

        foreach (resolve(WorkCenters::class)->all() as $center) {
            if ($center->name === $name) {
                return $center->id;
            }
        }

        throw new InvalidArgumentException("The demo company has no [{$name}] yet.");
    }
}
