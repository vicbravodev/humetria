<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Contracts\SeatingRoute;
use App\Modules\Telework\Contracts\Committee;
use App\Modules\Tenancy\Contracts\WorkCenters;

final readonly class CompanySeatingRoute implements SeatingRoute
{
    public function __construct(private ComplianceRows $rows, private Committee $committee, private WorkCenters $workCenters) {}

    public function progress(array $workCenterIds): array
    {
        $own = array_values(array_intersect($workCenterIds, array_column($this->workCenters->all(), 'id')));
        $progress = [];

        foreach (ComplianceFacts::ofMany($own, $this->committee) as $id => $facts) {
            $progress[$id] = RouteStages::ofFacts($facts, $this->rows->fromFacts($facts));
        }

        return $progress;
    }
}
