<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Engagement\Actions\RetrySummary;
use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Http\Requests\CommentsRequest;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Thresholds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The comments about the company, once the survey is closed: the AI summary for everyone who views climate and, for who
 * manages it, the original comments of each open question, paginated, from the minimum of comments. Under it, no text and
 * no count. Every view is audited with the survey and the question, never what was said.
 */
final class CommentsController extends Controller
{
    private const int PER_PAGE = 10;

    public function __construct(private readonly RecordAudit $audit, private readonly Thresholds $thresholds) {}

    public function show(CommentsRequest $request, mixed $tenant, string $survey, SurveyKind $kind): Response
    {
        $survey = $this->survey($survey, $kind);
        $item = $request->item();
        $available = $survey->status === SurveyStatus::Closed;
        $manages = Gate::allows('manage', $survey);
        $summary = $available ? CommentSummary::query()->where('survey_id', $survey->id)->whereNull('manager_group_id')->first() : null;
        $originals = $available && $manages ? $this->originals($survey, $item) : ['state' => 'hidden', 'page' => null];

        if ($available) {
            Log::info('engagement.comments_viewed', ['survey_id' => $survey->id, 'item' => $item]);
            $this->audit->handle('engagement.comments_viewed', $survey, ['item' => $item]);
        }

        return Inertia::render('engagement/comments', [
            'survey' => ['id' => $survey->id, 'name' => $survey->name, 'status' => $survey->status->value],
            'available' => $available,
            'item' => $item,
            'sensitiveMentions' => $available && $survey->sensitive_mentions,
            'summary' => $summary === null ? null : ['status' => $summary->status->value, 'themes' => $summary->status === SummaryStatus::Ready ? $summary->themes : null],
            'retryId' => $manages && $summary?->status === SummaryStatus::Failed ? $summary->id : null,
            'commentsState' => $originals['state'],
            'comments' => $originals['page'],
        ]);
    }

    public function retry(RetrySummary $retry, mixed $tenant, string $summary, SurveyKind $kind): RedirectResponse
    {
        // Another company's summary is not found, the same 404 as one that does not exist.
        $summary = CommentSummary::query()->findOrFail($summary);
        $survey = $this->survey($summary->survey_id, $kind);
        Gate::authorize('manage', $survey);

        $retry->handle($summary->id);

        return to_route('tenant.climate.comments', [$tenant, $survey->id]);
    }

    /**
     * The original comments of the question, only from the minimum of comments.
     *
     * @return array{state: string, page: \Illuminate\Pagination\LengthAwarePaginator<int, array{body: string}>|null}
     */
    private function originals(Survey $survey, string $item): array
    {
        // By id, a random UUID: neither the order of arrival nor of the people shows. Only the text is read.
        $query = Comment::query()->where('survey_id', $survey->id)->whereNull('comment_group_id')->where('item_key', $item)->orderBy('id');

        if (! $this->thresholds->comments($query->count())) {
            return ['state' => 'few', 'page' => null];
        }

        return ['state' => 'shown', 'page' => $query->paginate(self::PER_PAGE, ['body'])->withQueryString()->through(fn (Comment $comment): array => ['body' => $comment->body])];
    }

    private function survey(string $id, SurveyKind $kind): Survey
    {
        $survey = Survey::query()->findOrFail($id);
        abort_unless($survey->kind === $kind, 404);
        Gate::authorize('view', $survey);

        return $survey;
    }
}
