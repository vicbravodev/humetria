<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Actions\RecordAudit;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Jobs\SendFeedbackPublished;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Survey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Publishes «Nos dijeron / Haremos» of a closed survey to the people of the company: from now on their portal shows
 * what the company will do, and each one gets an email with their own link. A survey with no commitment is not
 * published: saying «we listened» with nothing to do would be worse than silence. Publishing again changes nothing and
 * emails nobody twice. Neither the log nor the audit trail carry what the commitments say.
 */
final readonly class PublishFeedback
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * @throws ValidationException when the survey is not closed or has no commitment to publish.
     */
    public function handle(string $surveyId, int $userId): Survey
    {
        return DB::transaction(function () use ($surveyId, $userId): Survey {
            $survey = Survey::query()->lockForUpdate()->findOrFail($surveyId);

            if ($survey->status !== SurveyStatus::Closed) {
                throw ValidationException::withMessages(['survey' => 'Se publica cuando la encuesta ya cerró.']);
            }

            if ($survey->results_published_at !== null) {
                return $survey;
            }

            $commitments = Commitment::query()->where('survey_id', $survey->id)->where('status', '!=', CommitmentStatus::Dropped)->count();

            if ($commitments === 0) {
                throw ValidationException::withMessages(['commitments' => 'Registra al menos un compromiso antes de publicar.']);
            }

            $survey->update(['results_published_at' => now()]);

            Log::info('engagement.feedback_published', ['survey_id' => $survey->id, 'commitments' => $commitments]);
            $this->recordAudit->handle('engagement.feedback_published', $survey, ['commitments' => $commitments, 'published_by' => $userId]);
            SendFeedbackPublished::dispatch($survey->id);

            return $survey;
        });
    }
}
