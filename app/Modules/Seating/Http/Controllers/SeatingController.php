<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\DeclareProfile;
use App\Modules\Seating\Http\Requests\ProfileRequest;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\SeatingPages;
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
    public function index(Request $request, mixed $tenant, CenterAccess $access, SeatingPages $pages): Response
    {
        Gate::authorize('view-seating');

        return Inertia::render('seating/index', $pages->index($access->userId($request)));
    }

    public function center(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, SeatingPages $pages): Response
    {
        Gate::authorize('view-seating');

        return Inertia::render('seating/center', $pages->center($access->find($request, $workCenter)));
    }

    public function updateProfile(ProfileRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, DeclareProfile $declare): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $declare->handle($center->id, $request->sector(), $request->noStandingPositions(), $request->note(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Declaración guardada.']);

        return redirect()->route('tenant.seating.center', [$tenant, $center->id]);
    }
}
