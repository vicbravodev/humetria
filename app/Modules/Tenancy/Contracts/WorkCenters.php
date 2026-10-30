<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * The current company's work centers, as other modules need them: to place people in one and to name it.
 */
interface WorkCenters
{
    /**
     * Every work center of the current company, by name, archived ones included.
     *
     * @return list<WorkCenterSummary>
     */
    public function all(): array;
}
