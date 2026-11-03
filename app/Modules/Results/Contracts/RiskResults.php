<?php

declare(strict_types=1);

namespace App\Modules\Results\Contracts;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Results\Data\AreaRisk;
use App\Modules\Results\Data\RiskBreakdown;

/**
 * The risk results of a NOM-035 evaluation of the current company as other modules read them: how many questionnaires
 * fall in each risk level (criterio C-14), only once it is closed (C-27) and only for a group as large as the minimum
 * (C-23, applied after the area filter). Never an answer, an average or anything that points to a person.
 */
interface RiskResults
{
    /**
     * The final score, categories and domains of the evaluation, or of one area when it is an area shown; the areas
     * shown and how many stay hidden. The state says why there are no figures when there are none.
     */
    public function forCampaign(CampaignRecord $campaign, ?string $areaId = null): RiskBreakdown;

    /**
     * The areas shown, with their levels by final score, category and domain: the same areas as `forCampaign` (minimum and
     * complementary suppression applied), from one read of the answers. Empty without figures: open, not scored or
     * under the minimum.
     *
     * @return list<AreaRisk>
     */
    public function areaGroups(CampaignRecord $campaign): array;
}
