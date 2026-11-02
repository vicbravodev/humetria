<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\DeclareProfile;
use App\Modules\Seating\Http\Requests\ProfileRequest;
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
 * The Ley Silla entry: the centers the person may see, and the page of one center. A center of another company, an
 * archived one, a made-up one and (for the Comisión) one it does not sit on are all a 404.
 */
final class SeatingController extends Controller
{
    public function index(Request $request, mixed $tenant, SeatingPages $pages): Response
    {
        Gate::authorize('view-seating');

        return Inertia::render('seating/index', $pages->index($this->userId($request)));
    }

    public function center(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, VisibleCenters $visible, SeatingPages $pages): Response
    {
        Gate::authorize('view-seating');

        return Inertia::render('seating/center', $pages->center($this->visibleCenter($request, $workCenter, $workCenters, $visible)));
    }

    public function updateProfile(ProfileRequest $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, VisibleCenters $visible, DeclareProfile $declare): RedirectResponse
    {
        $center = $this->visibleCenter($request, $workCenter, $workCenters, $visible);
        $declare->handle($center->id, $request->sector(), $request->noStandingPositions(), $request->note(), $this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Declaración guardada.']);

        return redirect()->route('tenant.seating.center', [$tenant, $center->id]);
    }

    private function visibleCenter(Request $request, string $id, WorkCenters $workCenters, VisibleCenters $visible): WorkCenterSummary
    {
        $center = $workCenters->findActive($id);
        abort_if($center === null, 404);
        $limit = $visible->limitedTo($this->userId($request));
        abort_unless($limit === null || in_array($center->id, $limit, true), 404);

        return $center;
    }

    private function userId(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user->id;
    }
}
