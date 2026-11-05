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
            $declaredOn = $facts->profile === null ? null : str_replace('.', '', $facts->profile->declared_on->isoFormat('D MMM YYYY'));
            $progress[$id] = RouteStages::from($this->rows->fromFacts($facts), $facts->profile->no_standing_positions ?? false, $facts->positions->isNotEmpty(), $declaredOn);
        }

        return $progress;
    }
}
