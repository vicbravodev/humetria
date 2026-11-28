<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

use App\Modules\Demos\Data\DemoLead;
use App\Modules\Demos\Data\DemoWaiting;

/** What the other modules may ask of the demo requests: read one, and say that a company was created from it. */
interface DemoLeads
{
    public function find(string $id): ?DemoLead;

    /**
     * The request each company was created from, by company id, for the ones that came from one.
     *
     * @param  list<string>  $tenantIds
     * @return array<string, DemoLead>
     */
    public function forCompanies(array $tenantIds): array;

    /**
     * Links the request to the company it became. False when it already had one: only the first conversion counts, even
     * when two people try at once.
     */
    public function markConverted(string $id, string $tenantId): bool;

    /**
     * How many requests each status holds, and all of them, for the platform's overview.
     *
     * @return array<string, int>
     */
    public function countsByStatus(): array;

    /** How many requests are new, and how many of them have waited more than 48 hours: what needs the team today. */
    public function waiting(): DemoWaiting;
}
