<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Modules\Tenancy\Models\WorkCenter;

final class CompanyWorkCenters implements WorkCenters
{
    public function all(): array
    {
        return array_values(WorkCenter::query()->orderBy('name')->get(['id', 'name', 'archived_at'])
            ->map(fn (WorkCenter $workCenter): WorkCenterSummary => new WorkCenterSummary($workCenter->id, $workCenter->name, $workCenter->archived_at !== null))
            ->all());
    }
}
