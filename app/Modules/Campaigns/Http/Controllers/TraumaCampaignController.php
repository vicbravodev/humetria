<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Actions\LaunchTraumaCampaign;
use App\Modules\Campaigns\Http\Requests\TraumaCampaignRequest;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guía I for the people of an event (criterio C-05): pick the work center, then who was there.
 */
final class TraumaCampaignController extends Controller
{
    public function create(Request $request, WorkCenters $workCenters, Roster $roster): Response
    {
        Gate::authorize('manage-campaigns');
        $chosen = $request->string('work_center')->toString();
        $workCenter = Str::isUuid($chosen) ? $workCenters->findActive($chosen) : null;
        $workCenterId = $workCenter?->id;
        $query = new RosterQuery(activeInWorkCenter: $workCenterId, search: $request->string('q')->trim()->limit(100, '')->toString());

        return Inertia::render('campaigns/trauma', [
            'workCenter' => $workCenter === null ? null : ['value' => $workCenter->id, 'label' => $workCenter->name],
            'people' => $workCenterId === null ? null : $roster->page($query)->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'area' => $person->areaName,
                'reachable' => $person->email !== null,
            ]),
            'filters' => ['q' => $query->search],
            'maxPeople' => TraumaCampaignRequest::MAX_PEOPLE,
            // Only when asked: to choose at once everyone the search found, on every page.
            'matching' => Inertia::optional(fn (): array => $workCenterId === null ? [] : $roster->matchingIds($query)),
            'minDeadline' => CampaignClock::today()->addDay()->format('Y-m-d'),
            'suggestedDeadline' => CampaignClock::today()->addWeek()->format('Y-m-d'),
        ]);
    }

    public function store(TraumaCampaignRequest $request, mixed $tenant, LaunchTraumaCampaign $launch): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $user = $request->user();
        abort_if($user === null, 403);

        $campaign = $launch->handle($request->workCenterId(), $request->employeeIds(), $request->closesOn(), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guía I enviada a las personas elegidas.']);

        return redirect()->route('tenant.campaigns.show', [$tenant, $campaign->id]);
    }
}
