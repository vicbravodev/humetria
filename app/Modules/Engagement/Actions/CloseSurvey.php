<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Actions\RecordAudit;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Events\SurveyClosed;
use App\Modules\Engagement\Exceptions\SurveyStateChanged;
use App\Modules\Engagement\Models\Survey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes a survey: no link works from now on, and its results exist from this moment. `$userId` is the person who closed
 * it, `null` when its last day ended. The lock waits for the answers being taken.
 */
final readonly class CloseSurvey
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * @throws SurveyStateChanged when it is not active any more.
     */
    public function handle(string $surveyId, ?int $userId = null): Survey
    {
        return DB::transaction(function () use ($surveyId, $userId): Survey {
            $survey = Survey::lockIn($surveyId, SurveyStatus::Active);
            $survey->update(['status' => SurveyStatus::Closed, 'closed_at' => now(), 'priorities' => []]);

            $automatic = $userId === null;
            Log::info('engagement.survey_closed', ['survey_id' => $survey->id, 'automatic' => $automatic]);
            $this->recordAudit->handle('engagement.survey_closed', $survey, ['automatic' => $automatic]);
            event(new SurveyClosed($survey->id, $survey->kind));

            return $survey;
        });
    }
}
