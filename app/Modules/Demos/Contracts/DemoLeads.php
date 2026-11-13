<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

use App\Modules\Demos\Data\DemoLead;

/** What the other modules may ask of the demo requests: read one, and say that a company was created from it. */
interface DemoLeads
{
    public function find(string $id): ?DemoLead;

    /**
     * Links the request to the company it became. False when it already had one: only the first conversion counts, even
     * when two people try at once.
     */
    public function markConverted(string $id, string $tenantId): bool;
}
