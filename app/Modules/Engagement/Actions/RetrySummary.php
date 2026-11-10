<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Exceptions\SurveyStateChanged;
use App\Modules\Engagement\Jobs\SummarizeComments;
use App\Modules\Engagement\Models\CommentSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tries again the summary of one group of comments, only when it failed: one that is ready is never made twice, and one
 * that is pending, withheld or off has nothing to retry.
 */
final readonly class RetrySummary
{
    /**
     * @throws SurveyStateChanged when the summary is not a failed one.
     */
    public function handle(string $summaryId): void
    {
        DB::transaction(function () use ($summaryId): void {
            $summary = CommentSummary::query()->lockForUpdate()->findOrFail($summaryId);

            if ($summary->status !== SummaryStatus::Failed) {
                throw new SurveyStateChanged;
            }

            $summary->update(['status' => SummaryStatus::Pending]);
            SummarizeComments::dispatch($summary->survey_id, $summary->manager_group_id)->afterCommit();
            Log::info('engagement.summary_retried', ['survey_id' => $summary->survey_id, 'group_id' => $summary->manager_group_id]);
        });
    }
}
