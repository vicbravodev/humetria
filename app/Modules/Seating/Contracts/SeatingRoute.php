<?php

declare(strict_types=1);

namespace App\Modules\Seating\Contracts;

use App\Modules\Tenancy\Data\RouteProgress;

/**
 * The Ley Silla route of the current company as the panel reads it: where each work center stands in its six stages.
 * Counts and states only, never a person.
 */
interface SeatingRoute
{
    /**
     * A center of another company, or one that does not exist, is left out.
     *
     * @param  list<string>  $workCenterIds
     * @return array<string, RouteProgress> by work center id
     */
    public function progress(array $workCenterIds): array;
}
