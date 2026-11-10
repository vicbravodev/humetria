<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Listeners;

use App\Modules\Engagement\Events\SurveyClosed;
use App\Modules\Engagement\Jobs\SummarizeComments;
use App\Modules\Engagement\Models\Comment;

/**
 * When a survey closes, the AI summary of its comments is made once: one job for the comments about the company and one
 * for each group of comments of a manager.
 */
final class QueueCommentSummaries
{
    public function handle(SurveyClosed $event): void
    {
        SummarizeComments::dispatch($event->surveyId, null);

        foreach (Comment::query()->where('survey_id', $event->surveyId)->whereNotNull('comment_group_id')->distinct()->get(['comment_group_id']) as $comment) {
            SummarizeComments::dispatch($event->surveyId, $comment->comment_group_id);
        }
    }
}
