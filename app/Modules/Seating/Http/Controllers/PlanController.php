<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\PlanPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The action plan of a work center: its measures and its pause agreements. RH and the Comisión of the center read it; the
 * viewer does not (a 403) and the Comisión of other centers does not find it (a 404).
 */
final class PlanController extends Controller
{
    public function index(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, PlanPages $pages): Response
    {
        Gate::authorize('assess-seating');
        $center = $access->find($request, $workCenter);

        return Inertia::render('seating/plan', $pages->index($tenant, $center, $request->string('edit')->toString() ?: null));
    }
}
