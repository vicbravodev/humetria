<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Contracts;

use App\Modules\Campaigns\Data\EvaluationStanding;

/**
 * Where each work center of the current company stands with its NOM-035 evaluations (num. 7.9: at least every two
 * years): one in preparation or running, or the day the next one is due after the last that closed.
 */
interface EvaluationSchedule
{
    /**
     * @param  list<string>  $workCenterIds
     * @return array<string, EvaluationStanding> by work center id; one never evaluated is left out.
     */
    public function forWorkCenters(array $workCenterIds): array;
}
