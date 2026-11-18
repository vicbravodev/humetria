<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Actions\RecordAudit;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Exceptions\SurveyCannotLaunch;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Engagement\Support\Thresholds;
use App\Modules\Tenancy\Contracts\GroupMinimums;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Launches a draft: invites everyone who is active, fixes the coarse group each person is in and leaves their links to
 * the queue. It needs enough people for a group to be shown at all (otherwise nobody could be told apart from the
 * whole), and a deadline from tomorrow to sixty days ahead. The survey keeps the company's minimum per group as it is
 * now: the invitation promises that number, so a later change of the company's never reaches it.
 */
final readonly class LaunchSurvey
{
    public const int MAX_DAYS = 60;

    public function __construct(
        private SnapshotAudience $snapshot,
        private QueueInvitations $queueInvitations,
        private Thresholds $thresholds,
        private RecordAudit $recordAudit,
        private GroupMinimums $groupMinimums,
    ) {}

    /**
     * @throws SurveyCannotLaunch when the deadline is out of range or there are too few people.
     */
    public function handle(string $surveyId, CarbonImmutable $closesOn, int $userId): Survey
    {
        return DB::transaction(function () use ($surveyId, $closesOn): Survey {
            // Under lock: a second launch (a double click, another tab) waits here and then finds it launched.
            $survey = Survey::lockIn($surveyId, SurveyStatus::Draft);
            $today = SurveyClock::day();

            if ($closesOn->lessThanOrEqualTo($today) || $closesOn->greaterThan($today->addDays(self::MAX_DAYS))) {
                throw SurveyCannotLaunch::badDeadline(self::MAX_DAYS);
            }

            $invited = $this->snapshot->handle($survey);
            $minimum = $this->groupMinimums->current();

            if (! $this->thresholds->met($invited)) {
                throw SurveyCannotLaunch::tooFewPeople($minimum);
            }

            $survey->update(['status' => SurveyStatus::Active, 'launched_at' => now(), 'closes_on' => $closesOn, 'invited' => $invited, 'min_group' => $minimum]);
            $this->queueInvitations->handle($survey, array_values(Invitation::query()->where('survey_id', $survey->id)->get(['id'])->map(fn (Invitation $invitation): string => $invitation->id)->all()));

            Log::info('engagement.survey_launched', ['survey_id' => $survey->id, 'invited' => $invited]);
            $this->recordAudit->handle('engagement.survey_launched', $survey, ['invited' => $invited]);

            return $survey;
        });
    }
}
