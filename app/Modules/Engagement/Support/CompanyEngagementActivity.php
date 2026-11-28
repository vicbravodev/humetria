<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Contracts\EngagementActivity;
use App\Modules\Engagement\Data\SurveyActivity;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use Carbon\CarbonImmutable;

final readonly class CompanyEngagementActivity implements EngagementActivity
{
    public function since(CarbonImmutable $since): SurveyActivity
    {
        // A bound date loses its offset: compare in UTC, as the columns are kept.
        $survey = Survey::query()->where('created_at', '>=', $since->utc())->orderByDesc('created_at')->orderByDesc('id')->first();

        if ($survey === null) {
            return new SurveyActivity(false, false, 0, 0, false);
        }

        return new SurveyActivity(
            true,
            $survey->status !== SurveyStatus::Draft,
            $survey->invited ?? 0,
            // The invitation's flag, as RH's panel counts it; the anonymous responses are never read.
            Invitation::query()->where('survey_id', $survey->id)->where('answered', true)->count(),
            $survey->status === SurveyStatus::Closed,
        );
    }
}
