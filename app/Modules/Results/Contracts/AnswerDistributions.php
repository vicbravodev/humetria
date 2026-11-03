<?php

declare(strict_types=1);

namespace App\Modules\Results\Contracts;

use App\Modules\Results\Data\SurveyDistribution;

/**
 * The answers of a confidential survey of the current company as other modules read them: counted per question and per
 * area, only for a group as large as the minimum (criterio C-23), never an answer.
 */
interface AnswerDistributions
{
    /**
     * The survey of a campaign for its whole center, or for one area when it is an area that may be shown (an area under
     * the minimum, or one the complementary suppression hides, is ignored: the center is returned). The minimum applies
     * after the filter. A question counts only the people who answered it: whoever said «no» to the gate answered none.
     */
    public function of(string $campaignId, ?string $areaId = null): SurveyDistribution;
}
