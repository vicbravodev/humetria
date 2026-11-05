<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Contracts\TeleworkRoute;
use App\Modules\Telework\Support\TeleworkPages;
use App\Modules\Telework\Support\VisibleCenters;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The NOM-037 entry: the centers the person may see with their progress, and the dashboard of one center. A center of
 * another company, an archived one, a made-up one and (for the Comisión) one it does not sit on are all a 404.
 */
final class TeleworkController extends Controller
{
    public function index(Request $request, mixed $tenant, VisibleCenters $visible, TeleworkPages $pages): Response
    {
        Gate::authorize('view-telework');
        $userId = $this->userId($request);

        // The Comisión sees only its queue (board V2-Comision).
        if ($visible->limitedTo($userId) !== null) {
            return Inertia::render('telework/queue', $pages->queue($tenant, $userId, $request->user()->name ?? ''));
        }

        return Inertia::render('telework/index', $pages->index($userId));
    }

    public function center(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, VisibleCenters $visible, TeleworkPages $pages): Response
    {
        Gate::authorize('view-telework');
        $userId = $this->userId($request);

        return Inertia::render('telework/center', $pages->center($tenant, $this->visibleCenter($workCenter, $userId, $workCenters, $visible), $userId));
    }

    /**
     * The NOM-037 route of one center (board V2-Ruta-N037). A center that has not answered «¿Te aplica?» is sent to answer
     * it, and one where it does not apply, to its certificate.
     */
    public function route(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, VisibleCenters $visible, TeleworkRoute $route, TeleworkPages $pages): Response|RedirectResponse
    {
        Gate::authorize('view-telework');
        $userId = $this->userId($request);
        $center = $this->visibleCenter($workCenter, $userId, $workCenters, $visible);
        $progress = $route->progress([$center->id])[$center->id];

        if (! $progress->applies) {
            return to_route($progress->answered ? 'tenant.telework.certificate' : 'tenant.telework.applies.show', [$tenant, $center->id]);
        }

        return Inertia::render('telework/route', $pages->route($tenant, $center, $progress, $userId));
    }

    /** «Si hoy llegara una inspección» of one center (board V3-Inspeccion). */
    public function inspection(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, VisibleCenters $visible, TeleworkPages $pages): Response
    {
        Gate::authorize('view-telework');
        $userId = $this->userId($request);

        return Inertia::render('telework/inspection', $pages->inspection($tenant, $this->visibleCenter($workCenter, $userId, $workCenters, $visible), $userId));
    }

    /** The center in the address, or a 404 when it is not an active one of this company the user may see. */
    private function visibleCenter(string $workCenter, int $userId, WorkCenters $workCenters, VisibleCenters $visible): WorkCenterSummary
    {
        $center = Str::isUuid($workCenter) ? $workCenters->findActive($workCenter) : null;
        abort_if($center === null, 404);
        $limit = $visible->limitedTo($userId);
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
