<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Actions\ReviewNotice;
use App\Modules\Telework\Http\Requests\NoticeReviewRequest;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\NoticeAccess;
use App\Modules\Telework\Support\VisibleCenters;
use App\Modules\Telework\Support\WorkplaceAddress;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * The written notices of the company's teleworkers (numerals 6.3, 6.7, 6.9), for RH and the Comisión of each center
 * (which sees only its own). The ones nobody reviewed come first. Descriptions are shown here and nowhere else.
 */
final class NoticeController extends Controller
{
    private const int PER_PAGE = 15;

    public function __construct(
        private readonly VisibleCenters $visible,
        private readonly NoticeAccess $access,
        private readonly WorkCenters $workCenters,
        private readonly Roster $roster,
        private readonly WorkplaceAddress $address,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('review-workplaces');
        $user = $request->user();
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($user === null, 403); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall

        $centers = [];

        foreach ($this->workCenters->all() as $center) {
            $centers[$center->id] = $center;
        }

        $limit = $this->visible->limitedTo($user->id);
        $only = $request->string('center')->toString();
        $selected = Str::isUuid($only) ? ($centers[$only] ?? null) : null;
        abort_if($selected !== null && $limit !== null && ! in_array($selected->id, $limit, true), 404);
        $ids = $selected === null ? $limit : [$selected->id];

        $page = WorkerNotice::query()
            ->when($ids !== null, fn ($query) => $query->whereIn('teleworker_id', Teleworker::query()->whereIn('work_center_id', $ids ?? [])->select('id')))
            ->orderByRaw('reviewed_at IS NOT NULL')
            ->orderByRaw('CASE WHEN reviewed_at IS NULL THEN submitted_at END ASC')
            ->orderByDesc('submitted_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $teleworkers = Teleworker::query()->whereIn('id', array_map(fn (WorkerNotice $notice): string => $notice->teleworker_id, $page->items()))->get(['id', 'employee_id', 'work_center_id'])->keyBy('id');
        // A whereIn ignores the keys of its values, and a null in it matches nothing.
        $people = $this->roster->findMany(array_values($teleworkers->map(fn (Teleworker $teleworker): string => $teleworker->employee_id)->all())); // @pest-mutate-ignore: UnwrapArrayValues
        $workplaces = Workplace::query()->whereIn('id', array_values(array_filter(array_map(fn (WorkerNotice $notice): ?string => $notice->new_workplace_id, $page->items()))))->get()->keyBy('id'); // @pest-mutate-ignore: UnwrapArrayValues,UnwrapArrayFilter
        $zone = config()->string('app.display_timezone');
        $canLaunch = Gate::allows('manage-campaigns');
        // Once per request: RH reviews everywhere, the Comisión only in the centers it sits on (already read as the limit).
        $manages = Gate::allows('manage-telework');

        return Inertia::render('telework/notices', [
            'notices' => $page->through(function (WorkerNotice $notice) use ($teleworkers, $people, $workplaces, $centers, $zone, $canLaunch, $manages, $limit): array {
                // The foreign keys guarantee the period of every notice and the employee of every period.
                $teleworker = $teleworkers->get($notice->teleworker_id) ?? throw new LogicException("Notice [{$notice->id}] has no period.");
                $person = $people[$teleworker->employee_id];
                $centerId = $teleworker->work_center_id;
                $workplace = $notice->new_workplace_id === null ? null : $workplaces->get($notice->new_workplace_id);

                return [
                    'id' => $notice->id,
                    'person' => $person->name,
                    'jobTitle' => $person->jobTitle,
                    'workCenter' => isset($centers[$centerId]) ? $centers[$centerId]->name : '',
                    'kindValue' => $notice->kind->value,
                    'kind' => $notice->kind->label(),
                    'writtenOn' => $notice->written_on->toDateString(),
                    'occurredOn' => $notice->occurred_on->toDateString(),
                    'description' => $notice->description,
                    'traumaticEvent' => $notice->traumatic_event,
                    'temporary' => $notice->temporary,
                    'newAddress' => $workplace === null ? null : $this->address->line($workplace),
                    'submittedOn' => $notice->submitted_at->setTimezone($zone)->toDateString(),
                    'reviewedOn' => $notice->reviewed_at?->setTimezone($zone)->toDateString(),
                    'reviewNote' => $notice->review_note,
                    // The Comisión sees only the notices of its centers, so for it the in_array() always holds.
                    'canReview' => $manages || ($limit !== null && in_array($centerId, $limit, true)), // @pest-mutate-ignore: BooleanAndToBooleanOr
                    // The Guía I is launched from the NOM-035 area: only who may open campaigns gets the way in.
                    'trauma' => $canLaunch && $notice->traumatic_event ? ['workCenter' => $centerId, 'employee' => $teleworker->employee_id] : null,
                ];
            }),
            'filters' => ['center' => $selected === null ? null : ['value' => $selected->id, 'label' => $selected->name]],
        ]);
    }

    public function review(NoticeReviewRequest $request, mixed $tenant, string $notice, ReviewNotice $review): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall

        $review->handle($this->access->reviewable($user->id, $notice), $request->string('note')->toString(), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Revisión registrada.']);

        return back();
    }
}
