<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\DeclareProfile;
use App\Modules\Seating\Http\Requests\ProfileRequest;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\ComplianceFacts;
use App\Modules\Seating\Support\ComplianceRows;
use App\Modules\Seating\Support\RoutePages;
use App\Modules\Seating\Support\RouteStages;
use App\Modules\Seating\Support\SeatingPages;
use App\Modules\Telework\Contracts\Committee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Ley Silla entry: the centers the person may see, the route of one center and the page of one center. A center of another company, an
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

    /**
     * The Ley Silla route of one center (board V2-Ruta-Silla). Where it does not start, its certificate or «¿Te aplica?».
     */
    public function route(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, ComplianceRows $rows, Committee $committee, RoutePages $pages): Response|RedirectResponse
    {
        Gate::authorize('view-seating');
        $center = $access->find($request, $workCenter);
        $facts = ComplianceFacts::of($center->id, $committee);
        $requirements = $rows->fromFacts($facts);
        $progress = RouteStages::ofFacts($facts, $requirements);

        if (! $progress->applies) {
            return $progress->answered
                ? to_route('tenant.seating.certificate', [$tenant, $center->id])
                : to_route('tenant.work-centers.applies', [$tenant, $center->id, 'norm' => 'seating']);
        }

        return Inertia::render('seating/route', $pages->route($tenant, $center, $facts, $requirements, $progress));
    }

    /** «Si hoy llegara una inspección» of one center (board V3-Inspeccion). */
    public function inspection(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, ComplianceRows $rows, RoutePages $pages): Response
    {
        Gate::authorize('view-seating');
        $center = $access->find($request, $workCenter);

        return Inertia::render('seating/inspection', $pages->inspection($tenant, $center, $rows->for($center->id)));
    }

    public function updateProfile(ProfileRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, DeclareProfile $declare): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $declare->handle($center->id, $request->sector(), $request->noStandingPositions(), $request->note(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Declaración guardada.']);

        return redirect()->route('tenant.seating.center', [$tenant, $center->id]);
    }
}
