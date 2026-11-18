<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Contracts;

use App\Modules\Engagement\Data\SurveyStanding;

/**
 * Where the current company stands with its climate and its pulse surveys, as the panel of each shows it to RH: states
 * and counts of the whole company, never an answer, a comment, a score or a group.
 */
interface EngagementStanding
{
    public function climate(): SurveyStanding;

    public function pulse(): SurveyStanding;
}
