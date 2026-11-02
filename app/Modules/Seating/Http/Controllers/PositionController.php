<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Seating\Actions\AssignPeople;
use App\Modules\Seating\Actions\SavePosition;
use App\Modules\Seating\Actions\UnassignPerson;
use App\Modules\Seating\Http\Requests\AssignmentRequest;
use App\Modules\Seating\Http\Requests\PositionRequest;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\SeatingPages;
use App\Modules\Seating\Support\VisibleCenters;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The standing positions of a work center and who holds them. A position of another company, of an archived center, a
 * made-up one and (for the Comisión) one of a center it does not sit on are all a 404.
 */
final class PositionController extends Controller
{
    public function create(mixed $tenant, string $workCenter, WorkCenters $workCenters, SeatingPages $pages): Response
    {
        Gate::authorize('manage-seating');
        $center = $workCenters->findActive($workCenter);
        abort_if($center === null, 404);

        return Inertia::render('seating/position', $pages->position($center, null, null));
    }

    public function show(Request $request, mixed $tenant, string $position, WorkCenters $workCenters, VisibleCenters $visible, SeatingPages $pages): Response
    {
        Gate::authorize('view-seating');
        [$found, $center] = $this->visiblePosition($request, $position, $workCenters, $visible);

        return Inertia::render('seating/position', $pages->position($center, $found, $pages->people($found)));
    }

    public function store(PositionRequest $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, SavePosition $save): RedirectResponse
    {
        $center = $workCenters->findActive($workCenter);
        abort_if($center === null, 404);
        $saved = $save->handle($request->toData(null, $center->id));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Puesto guardado.']);

        return redirect()->route('tenant.seating.positions.show', [$tenant, $saved->id]);
    }

    public function update(PositionRequest $request, mixed $tenant, string $position, SavePosition $save): RedirectResponse
    {
        $found = StandingPosition::query()->find($position);
        abort_if($found === null, 404);
        $save->handle($request->toData($found->id, $found->work_center_id));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Puesto guardado.']);

        return redirect()->route('tenant.seating.positions.show', [$tenant, $found->id]);
    }

    /**
     * The people of the position's center who match what was typed, for the field that picks them.
     *
     * @return list<array{value: string, label: string}>
     */
    public function employees(Request $request, mixed $tenant, string $position, Roster $roster): array
    {
        Gate::authorize('manage-seating');
        $found = StandingPosition::query()->find($position);
        abort_if($found === null, 404);
        $query = new RosterQuery(activeInWorkCenter: $found->work_center_id, search: $request->string('q')->trim()->limit(100, '')->toString());

        // The items of a page are already a list.
        return array_values(array_map( // @pest-mutate-ignore: UnwrapArrayValues
            fn (RosterEntry $person): array => ['value' => $person->id, 'label' => $person->name],
            $roster->page($query, perPage: 20)->items(),
        ));
    }

    public function assign(AssignmentRequest $request, mixed $tenant, string $position, AssignPeople $assign): RedirectResponse
    {
        $found = StandingPosition::query()->find($position);
        abort_if($found === null, 404);
        $assign->handle($found, $request->employeeIds());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Personas asignadas al puesto.']);

        return redirect()->route('tenant.seating.positions.show', [$tenant, $found->id]);
    }

    public function unassign(mixed $tenant, string $assignment, UnassignPerson $unassign): RedirectResponse
    {
        Gate::authorize('manage-seating');
        $found = PositionAssignment::query()->whereNull('ends_on')->find($assignment);
        abort_if($found === null, 404);
        $unassign->handle($found);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona quitada del puesto.']);

        return redirect()->route('tenant.seating.positions.show', [$tenant, $found->position_id]);
    }

    /**
     * @return array{StandingPosition, WorkCenterSummary}
     */
    private function visiblePosition(Request $request, string $id, WorkCenters $workCenters, VisibleCenters $visible): array
    {
        $position = StandingPosition::query()->find($id);
        abort_if($position === null, 404);
        $center = $workCenters->findActive($position->work_center_id);
        abort_if($center === null, 404);
        $user = $request->user();
        abort_if($user === null, 403);
        $limit = $visible->limitedTo($user->id);
        abort_unless($limit === null || in_array($center->id, $limit, true), 404);

        return [$position, $center];
    }
}
