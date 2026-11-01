<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Telework\Actions\RegisterTraining;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Enums\TrainingModality;
use App\Modules\Telework\Enums\TrainingTopic;
use App\Modules\Telework\Http\Requests\TrainingRequest;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Training;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Telework\Support\TrainingDue;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The trainings of the company (numerals 5.9 and 8.1) and, beside them, when each person of the roster is due for theirs.
 * Only whoever manages Telework. Nothing is edited or deleted: a training is evidence of the yearly obligation.
 */
final class TrainingController extends Controller
{
    private const int PER_PAGE = 15;

    public function __construct(private readonly WorkCenters $workCenters, private readonly Roster $roster, private readonly TrainingDue $due) {}

    public function index(Request $request): Response
    {
        Gate::authorize('manage-telework');
        $centers = [];

        foreach ($this->workCenters->all() as $center) {
            $centers[$center->id] = $center;
        }

        $only = $request->string('center')->toString();
        $selected = Str::isUuid($only) ? ($centers[$only] ?? null) : null;
        $zone = config()->string('app.display_timezone');
        $today = CarbonImmutable::now($zone)->startOfDay();

        $trainings = Training::query()
            ->when($selected, fn (Builder $query, WorkCenterSummary $center) => $query->where('work_center_id', $center->id))
            ->withCount('attendees')
            ->orderByDesc('held_on')->orderByDesc('created_at')->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('telework/trainings', [
            'trainings' => $trainings->through(fn (Training $training): array => [
                'id' => $training->id,
                'heldOn' => $training->held_on->toDateString(),
                'modality' => $training->modality->label(),
                'workCenter' => ['id' => $training->work_center_id, 'name' => isset($centers[$training->work_center_id]) ? $centers[$training->work_center_id]->name : ''],
                'topics' => array_map(fn (TrainingTopic $topic): string => $topic->label(), $training->topics),
                'attendees' => $training->attendees_count,
                'hasEvidence' => $training->evidence_path !== null,
            ]),
            'people' => $this->people($request, $selected, $centers, $today),
            'filters' => ['center' => $selected === null ? null : ['value' => $selected->id, 'label' => $selected->name]],
            'uncovered' => $selected === null ? null : array_map(fn (TrainingTopic $topic): array => ['value' => $topic->value, 'label' => $topic->label()], $this->due->uncoveredTopics($selected->id, $today)),
            'today' => $today->toDateString(),
            'topics' => array_map(fn (TrainingTopic $topic): array => ['value' => $topic->value, 'label' => $topic->label()], TrainingTopic::cases()),
            'modalities' => array_map(fn (TrainingModality $modality): array => ['value' => $modality->value, 'label' => $modality->label()], TrainingModality::cases()),
        ]);
    }

    public function store(TrainingRequest $request, mixed $tenant, RegisterTraining $register): RedirectResponse
    {
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($request->user() === null, 403); // @pest-mutate-ignore

        $register->handle($request->string('work_center_id')->toString(), $request->heldOn(), $request->modality(), $request->topics(), $request->employeeIds(), $request->evidenceFile(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Capacitación registrada.']);

        return redirect()->route('tenant.telework.trainings.index', $tenant);
    }

    public function evidence(mixed $tenant, string $training, FileStore $files): StreamedResponse
    {
        Gate::authorize('manage-telework');
        $found = Str::isUuid($training) ? Training::query()->find($training) : null;
        abort_if($found === null || $found->evidence_path === null, 404);

        $response = $files->stream($found->evidence_path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * The people of the roster, the soonest due first, a page at a time.
     *
     * @param  array<string, WorkCenterSummary>  $centers
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function people(Request $request, ?WorkCenterSummary $selected, array $centers, CarbonImmutable $today): LengthAwarePaginator
    {
        // ponytail: the due day is derived, so the roster (a few light columns per person) is sorted in memory; a SQL
        // expression and ORDER BY when a company has tens of thousands of teleworkers.
        // A fresh get() is already a list.
        $roster = array_values(Teleworker::query()->where('status', TeleworkerStatus::Active) // @pest-mutate-ignore: UnwrapArrayValues
            ->when($selected, fn (Builder $query, WorkCenterSummary $center) => $query->where('work_center_id', $center->id))
            ->get(['id', 'employee_id', 'work_center_id', 'started_at'])->all());
        $schedule = $this->due->schedule($roster);
        usort($roster, fn (Teleworker $a, Teleworker $b): int => [$schedule[$a->id]['due'], $a->id] <=> [$schedule[$b->id]['due'], $b->id]);

        $page = LengthAwarePaginator::resolveCurrentPage('people_page');
        $slice = array_slice($roster, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $names = $this->roster->findMany(array_map(fn (Teleworker $teleworker): string => $teleworker->employee_id, $slice));

        $rows = array_map(fn (Teleworker $teleworker): array => $this->row($teleworker, $schedule[$teleworker->id]['due'], $schedule[$teleworker->id]['last'], $names, $centers, $today), $slice);

        return (new LengthAwarePaginator($rows, count($roster), self::PER_PAGE, $page, ['path' => $request->url(), 'pageName' => 'people_page']))->withQueryString();
    }

    /**
     * @param  array<string, RosterEntry>  $names
     * @param  array<string, WorkCenterSummary>  $centers
     * @return array<string, mixed>
     */
    private function row(Teleworker $teleworker, CarbonImmutable $due, ?string $last, array $names, array $centers, CarbonImmutable $today): array
    {
        // The foreign key guarantees the employee of every period.
        $person = $names[$teleworker->employee_id];
        $standing = $this->due->standing($due, $today);

        return [
            'id' => $teleworker->id,
            'name' => $person->name,
            'jobTitle' => $person->jobTitle,
            'workCenter' => isset($centers[$teleworker->work_center_id]) ? $centers[$teleworker->work_center_id]->name : '',
            'lastTrainingOn' => $last,
            'dueOn' => $due->toDateString(),
            'status' => $standing->value,
            'statusLabel' => $standing->label(),
        ];
    }
}
