<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Support\TeleworkPages;
use App\Modules\Telework\Support\VisibleCenters;
use App\Modules\Tenancy\Contracts\WorkCenters;
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
    public function index(Request $request, mixed $tenant, TeleworkPages $pages): Response
    {
        Gate::authorize('view-telework');

        return Inertia::render('telework/index', $pages->index($this->userId($request)));
    }

    public function center(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, VisibleCenters $visible, TeleworkPages $pages): Response
    {
        Gate::authorize('view-telework');
        $userId = $this->userId($request);
        $center = Str::isUuid($workCenter) ? $workCenters->findActive($workCenter) : null;
        abort_if($center === null, 404);
        $limit = $visible->limitedTo($userId);
        abort_unless($limit === null || in_array($center->id, $limit, true), 404);

        return Inertia::render('telework/center', $pages->center($tenant, $center, $userId));
    }

    private function userId(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user->id;
    }
}
