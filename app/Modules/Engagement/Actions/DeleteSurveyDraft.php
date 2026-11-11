<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Survey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Throws away a draft. A launched survey is never deleted: it has people who were invited and answers.
 */
final class DeleteSurveyDraft
{
    public function handle(string $surveyId): void
    {
        DB::transaction(fn () => Survey::lockIn($surveyId, SurveyStatus::Draft)->delete());
        Log::info('engagement.survey_draft_deleted', ['survey_id' => $surveyId]);
    }
}
