<?php

declare(strict_types=1);

namespace App\Modules\Results\Contracts;

use App\Modules\Campaigns\Data\CampaignRecord;

/**
 * The results of the current company's evaluations as other modules read them: counted by risk level and only for a
 * group as large as the minimum (criterio C-23), never an answer.
 */
interface EvaluationResults
{
    /**
     * The campaigns, of those given, whose 7.7 report still lacks what HR fills in (num. 7.7 b, c, f, g, h).
     *
     * @param  list<string>  $campaignIds
     * @return list<string> in the order given
     */
    public function withoutReportDetails(array $campaignIds): array;

    /**
     * How many questionnaires of a closed evaluation fall in each risk level of the final score, from Nulo to Muy
     * alto; null while it runs, without Guía II or III, or with fewer questionnaires than the minimum.
     *
     * @return list<int>|null
     */
    public function finalLevels(CampaignRecord $campaign): ?array;
}
