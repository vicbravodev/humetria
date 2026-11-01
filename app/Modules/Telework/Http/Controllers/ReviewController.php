<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Actions\ReviewWorkplace;
use App\Modules\Telework\Http\Requests\ReviewRequest;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\ReviewAccess;
use App\Modules\Telework\Support\ReviewDetail;
use App\Modules\Telework\Support\WorkplaceAddress;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Comisión reviews the checklists of its work center. Everything here is for the assigned members of that center
 * (`ReviewAccess`); a checklist or workplace outside the company does not exist.
 */
final class ReviewController extends Controller
{
    public function __construct(private readonly ReviewAccess $access) {}

    public function index(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, Roster $roster, WorkplaceAddress $address): Response
    {
        $center = Str::isUuid($workCenter) ? $workCenters->findActive($workCenter) : null;
        abort_if($center === null, 404);
        $this->access->member($this->userId($request), $center->id);

        // Oldest first, so nothing waits behind newer lists. A retired workplace can no longer be reviewed.
        $page = ChecklistApplication::query()->awaitingReview()
            ->whereIn('workplace_id', Workplace::query()->whereIn('teleworker_id', Teleworker::query()->where('work_center_id', $center->id)->select('id'))->select('id'))
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
        $workplaces = [];
        $employees = [];

        foreach (Workplace::query()->whereIn('id', array_map(fn (ChecklistApplication $application): string => $application->workplace_id, $page->items()))->get() as $workplace) {
            $workplaces[$workplace->id] = $workplace;
        }

        // A whereIn ignores the keys and the repetitions of its values, here and in findMany() below.
        foreach (Teleworker::query()->whereIn('id', array_map(fn (Workplace $workplace): string => $workplace->teleworker_id, array_values($workplaces)))->get() as $teleworker) { // @pest-mutate-ignore: UnwrapArrayValues
            $employees[$teleworker->id] = $teleworker->employee_id;
        }

        $people = $roster->findMany(array_values(array_unique($employees))); // @pest-mutate-ignore: UnwrapArrayValues,UnwrapArrayUnique

        return Inertia::render('telework/reviews', [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'applications' => $page->through(function (ChecklistApplication $application) use ($workplaces, $employees, $people, $address): array {
                $workplace = $workplaces[$application->workplace_id] ?? throw new ModelNotFoundException;
                // The foreign keys guarantee the period of every workplace and the employee of every period.
                $person = $people[$employees[$workplace->teleworker_id]];

                return [
                    'id' => $application->id,
                    'person' => $person->name,
                    'jobTitle' => $person->jobTitle,
                    'workplace' => $address->line($workplace),
                    'section' => $application->section->value,
                    // The inbox lists only sent lists: the ?-> only narrows the type.
                    'submittedOn' => $application->submitted_at?->setTimezone(config()->string('app.display_timezone'))->toDateString(), // @pest-mutate-ignore: RemoveNullSafeOperator
                ];
            }),
        ]);
    }

    public function show(Request $request, mixed $tenant, string $application, ReviewDetail $detail, WorkCenters $workCenters): Response
    {
        $found = Str::isUuid($application) ? ChecklistApplication::query()->whereNotNull('submitted_at')->find($application) : null;
        abort_if($found === null, 404);
        $workplace = Workplace::query()->findOrFail($found->workplace_id);
        $center = $this->access->workCenterOf($workplace);
        $this->access->member($this->userId($request), $center);
        $name = $workCenters->profile($center)->name ?? '';

        return Inertia::render('telework/review', [
            'workCenter' => ['id' => $center, 'name' => $name],
            'review' => $detail->for($found),
        ]);
    }

    public function store(ReviewRequest $request, mixed $tenant, string $application, ReviewWorkplace $review): RedirectResponse
    {
        // ReviewRequest::authorize() already answered 404 for what is not there and checked the seat: this only gives the
        // controller its model.
        $found = Str::isUuid($application) ? ChecklistApplication::query()->find($application) : null;
        abort_if($found === null, 404); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall
        $workplace = Workplace::query()->findOrFail($found->workplace_id);
        $this->access->member($this->userId($request), $this->access->workCenterOf($workplace)); // @pest-mutate-ignore: RemoveMethodCall

        $review->handle($workplace, $found, $request->decision(), $request->notes(), $request->actionPlan(), $request->planDueOn(), $this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Decisión registrada.']);

        return redirect()->route('tenant.telework.reviews.index', [$tenant, $this->access->workCenterOf($workplace)]);
    }

    public function remoteCheck(ReviewRequest $request, mixed $tenant, string $workplace, ReviewWorkplace $review): RedirectResponse
    {
        // ReviewRequest::authorize() already answered 404 for what is not there and checked the seat: this only gives the
        // controller its model.
        $found = Str::isUuid($workplace) ? Workplace::query()->find($workplace) : null;
        abort_if($found === null, 404); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall
        $this->access->member($this->userId($request), $this->access->workCenterOf($found)); // @pest-mutate-ignore: RemoveMethodCall

        $review->handle($found, null, $request->decision(), $request->notes(), $request->actionPlan(), $request->planDueOn(), $this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comprobación a distancia registrada.']);

        return back();
    }

    private function userId(Request $request): int
    {
        $user = $request->user();
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($user === null, 403); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall

        return $user->id;
    }
}
