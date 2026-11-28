<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Contracts;

use App\Modules\Engagement\Data\SurveyActivity;
use Carbon\CarbonImmutable;

/**
 * Where the survey the current company created since a moment stands, for the demo guide: states and the invitations
 * answered, as RH sees them. Never an answer.
 */
interface EngagementActivity
{
    /** The newest climate or pulse survey created since `$since`. */
    public function since(CarbonImmutable $since): SurveyActivity;
}
