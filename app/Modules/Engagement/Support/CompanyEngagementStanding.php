<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Contracts\EngagementStanding;
use App\Modules\Engagement\Data\SurveyStanding;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;

/** The survey in progress and the last closed one of each kind, with the counts of people of the panel. */
final readonly class CompanyEngagementStanding implements EngagementStanding
{
    public function __construct(private Thresholds $thresholds) {}

    public function climate(): SurveyStanding
    {
        return $this->of(SurveyKind::Climate);
    }

    public function pulse(): SurveyStanding
    {
        return $this->of(SurveyKind::Pulse);
    }

    private function of(SurveyKind $kind): SurveyStanding
    {
        $current = Survey::query()->where('kind', $kind)->whereIn('status', [SurveyStatus::Draft, SurveyStatus::Active])->first();
        $open = $current?->status === SurveyStatus::Active ? $current : null;
        $last = Survey::query()->where('kind', $kind)->where('status', SurveyStatus::Closed)->orderByDesc('closed_at')->orderByDesc('id')->first();

        return new SurveyStanding(
            $current?->status === SurveyStatus::Draft,
            $open?->closes_on?->toDateString(),
            $open->invited ?? 0,
            $open === null ? 0 : self::answered($open),
            $last?->closed_at?->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            $last !== null && $this->thresholds->met(self::answered($last)),
        );
    }

    private static function answered(Survey $survey): int
    {
        return Invitation::query()->where('survey_id', $survey->id)->where('answered', true)->count();
    }
}
