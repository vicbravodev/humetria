<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Results\CutGroups;
use App\Modules\Engagement\Support\Results\ManagerFeedback;
use App\Modules\Engagement\Support\Results\SurveyResults;
use App\Modules\Engagement\Support\Thresholds;
use App\Modules\Organization\Contracts\Roster;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The results by manager, for Recursos Humanos: the managers whose group of people is shown, listed by name and never as a
 * ranking, and the feedback of each. Who manages climate also reads the original comments of the group, from the minimum
 * of comments; whoever else only sees the AI summary. Every view of a manager is audited.
 */
final class ManagerResultsController extends Controller
{
    private const int PER_PAGE = 10;

    public function __construct(private readonly RecordAudit $audit, private readonly Roster $roster, private readonly Thresholds $thresholds) {}

    public function index(mixed $tenant, string $survey, SurveyKind $kind): Response
    {
        $survey = $this->survey($survey, $kind);
        $results = SurveyResults::for($survey);
        $groups = $results->available() ? $results->managerGroups() : null;
        $rows = [];
        foreach ($this->shownNames($groups) as $id => $name) {
            $rows[] = ['id' => $id, 'name' => $name];
        }
        $page = LengthAwarePaginator::resolveCurrentPage();

        return Inertia::render('engagement/managers', [
            'kind' => $kind->value,
            'survey' => ['id' => $survey->id, 'name' => $survey->name, 'status' => $survey->status->value],
            'available' => $results->available(),
            // ponytail: one read of the names of every shown group; a company with thousands of managers wants a page query in Roster.
            'managers' => (new LengthAwarePaginator(array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE), count($rows), self::PER_PAGE, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]))->withQueryString(),
            'hasHidden' => $groups->hasHidden ?? false,
        ]);
    }

    public function show(mixed $tenant, string $survey, string $manager, SurveyKind $kind): Response
    {
        $survey = $this->survey($survey, $kind);
        $feedback = ManagerFeedback::for($survey, $manager);
        // A group that is not shown is a manager that does not exist.
        abort_if($feedback === null, 404);

        Log::info('engagement.manager_results_viewed', ['survey_id' => $survey->id, 'manager_id' => $manager]);
        $this->audit->handle('engagement.manager_results_viewed', $survey, ['manager_id' => $manager]);

        $comments = $this->comments($survey, $manager);
        $names = $this->shownNames(SurveyResults::for($survey)->managerGroups());
        $ids = array_keys($names);
        $at = (int) array_search($manager, $ids, true);
        $neighbor = fn (int $position): ?array => isset($ids[$position]) ? ['id' => $ids[$position], 'name' => $names[$ids[$position]]] : null;

        return Inertia::render('engagement/manager', [
            'kind' => $kind->value,
            'survey' => ['id' => $survey->id, 'name' => $survey->name, 'status' => $survey->status->value],
            'manager' => ['id' => $manager, 'name' => $this->roster->find($manager)->name ?? 'Sin nombre'],
            'feedback' => [
                'items' => $feedback->items,
                'strengths' => $feedback->strengths,
                'opportunities' => $feedback->opportunities,
                'line' => $feedback->line,
                'summary' => $feedback->summaryStatus === null ? null : ['status' => $feedback->summaryStatus->value, 'themes' => $feedback->themes],
            ],
            // The managers before and after by name, among the shown ones only: a way through them, never a ranking.
            'neighbors' => ['previous' => $neighbor($at - 1), 'next' => $neighbor($at + 1)],
            'commentsState' => $comments['state'],
            'comments' => $comments['page'],
        ]);
    }

    /**
     * The original comments of the group, only for who manages climate and only from the minimum of comments.
     *
     * @return array{state: string, page: LengthAwarePaginator<int, array{body: string}>|null}
     */
    private function comments(Survey $survey, string $manager): array
    {
        if (! Gate::allows('manage', $survey)) {
            return ['state' => 'hidden', 'page' => null];
        }

        // By id, a random UUID: neither the order of arrival nor of the people shows. Only the text is read.
        $query = Comment::query()->where('survey_id', $survey->id)->where('comment_group_id', $manager)->whereIn('item_key', ['manager.keep', 'manager.change'])->orderBy('id');

        if (! $this->thresholds->comments($query->count())) {
            return ['state' => 'few', 'page' => null];
        }

        return ['state' => 'shown', 'page' => $query->paginate(self::PER_PAGE, ['body'])->withQueryString()->through(fn (Comment $comment): array => ['body' => $comment->body])];
    }

    /**
     * The managers whose group is shown, by name.
     *
     * @return array<string, string>
     */
    private function shownNames(?CutGroups $groups): array
    {
        $names = [];
        foreach ($this->roster->findMany(array_keys($groups->visible ?? [])) as $id => $person) {
            $names[$id] = $person->name;
        }
        asort($names);

        return $names;
    }

    private function survey(string $id, SurveyKind $kind): Survey
    {
        // Another company's survey is not found, the same 404 as one that does not exist.
        $survey = Survey::query()->findOrFail($id);
        abort_unless($survey->kind === $kind, 404);
        Gate::authorize('view', $survey);

        return $survey;
    }
}
