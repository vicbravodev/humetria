<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\RoutePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The route of one standard in one work center (boards V3-Movil and V3-AlDia). An archived center, a made-up one and
 * another company's are a 404, as is a standard it does not know (the route only takes the three). NOM-037 and Ley Silla
 * are sent to their own pages.
 */
final class RouteController extends Controller
{
    public function show(Request $request, Tenant $tenant, string $workCenter, string $norm, WorkCenters $workCenters, RoutePage $page): Response|RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $center = $workCenters->findActive($workCenter);
        abort_if($center === null, 404);

        // NOM-037 and Ley Silla have their own route pages (boards V2-Ruta-N037 and V2-Ruta-Silla): one page per route, wide
        // or on a phone.
        if ($norm !== 'nom035') {
            return to_route($norm === 'nom037' ? 'tenant.telework.route' : 'tenant.seating.route', [$tenant->slug, $center->id]);
        }

        return Inertia::render('tenancy/route', $page->props($tenant->slug, $center, seesScreenings: $user->can('view-trauma-screenings')));
    }
}
