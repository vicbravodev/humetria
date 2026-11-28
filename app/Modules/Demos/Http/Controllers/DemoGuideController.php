<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Demos\Actions\RewindDemoStep;
use App\Modules\Demos\Actions\SkipDemoStep;
use App\Modules\Demos\Contracts\DemoShowcase;
use App\Modules\Demos\Http\Requests\DemoGuideRequest;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the dock of the guide calls: the guide as the panel shares it (`null` once the session is over; the poll records no
 * visit), and «Saltar paso» or going back to a step, which answer with the guide as it stands then. For the Vacaciones tour,
 * the people of the session's center to pick from, and opening the portal of one of them on the presenter's computer.
 */
final class DemoGuideController extends Controller
{
    public const int OPTIONS_LIMIT = 20;

    public function show(DemoGuideRequest $request): JsonResponse
    {
        return $this->guide($request);
    }

    public function skip(DemoGuideRequest $request, string $step, SkipDemoStep $skip): JsonResponse
    {
        abort_unless($skip->handle($step), 404);

        return $this->guide($request);
    }

    public function rewind(DemoGuideRequest $request, string $step, RewindDemoStep $rewind): JsonResponse
    {
        abort_unless($rewind->handle($step), 404);

        return $this->guide($request);
    }

    /** Leaves the panel for the person's portal, in the same tab: a new one-use link, opened right away and never shown. */
    public function portal(DemoGuideRequest $request, string $employee, DemoShowcase $showcase): Response
    {
        return Inertia::location($showcase->portalLinkFor($employee) ?? abort(404));
    }

    /**
     * The active people of the session's center whose name or number has what was typed, a few at a time: names only.
     *
     * @return list<array{value: string, label: string}>
     */
    public function people(DemoGuideRequest $request, DemoCompanies $demos, Companies $companies, Roster $roster): array
    {
        $center = DemoSession::active()->work_center_id ?? abort(404);
        // The call comes from outside the company's routes: it reads the demo, the only company the presenter may enter.
        $companies->enter($demos->id() ?? abort(404));
        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $people = $roster->page(new RosterQuery(activeInWorkCenter: $center, search: $search), self::OPTIONS_LIMIT)->items();

        return array_values(array_map(fn (RosterEntry $person): array => ['value' => $person->id, 'label' => $person->name], $people));
    }

    /** The guide as it stands after the change, so the dock need not wait for its next poll. */
    private function guide(DemoGuideRequest $request): JsonResponse
    {
        $guide = $request->guide()->forCurrentRequest();

        // A literal null, not the empty object Laravel makes of it.
        return $guide === null ? JsonResponse::fromJsonString('null') : new JsonResponse($guide);
    }
}
