<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sends the link again to whoever has not answered and was not cancelled: the same link, so nobody halfway through
 * loses theirs. It is manual: the survey never reminds by itself.
 */
final readonly class RemindPending
{
    public function __construct(private QueueInvitations $queueInvitations) {}

    /**
     * @return int how many links were queued.
     */
    public function handle(string $surveyId): int
    {
        return DB::transaction(function () use ($surveyId): int {
            $survey = Survey::lockIn($surveyId, SurveyStatus::Active);
            $pending = array_values(Invitation::query()->where('survey_id', $survey->id)->where('answered', false)->where('cancelled', false)->get(['id'])->map(fn (Invitation $invitation): string => $invitation->id)->all());
            $queued = $this->queueInvitations->handle($survey, $pending);

            Log::info('engagement.survey_reminded', ['survey_id' => $survey->id, 'pending' => count($pending), 'queued' => $queued]);

            return $queued;
        });
    }
}
