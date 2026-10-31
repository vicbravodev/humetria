<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Contracts\WorkCenters;

/**
 * A workplace's address in one line, the same on RH's page and in the person's portal.
 */
final readonly class WorkplaceAddress
{
    public function __construct(private WorkCenters $workCenters) {}

    public function line(Workplace $workplace): string
    {
        $number = $workplace->interior_number === null ? $workplace->exterior_number : "{$workplace->exterior_number}, int. {$workplace->interior_number}";
        $state = $this->workCenters->states()[$workplace->state] ?? $workplace->state;

        return "{$workplace->street} {$number}, {$workplace->neighborhood}, {$workplace->municipality}, {$state}";
    }
}
