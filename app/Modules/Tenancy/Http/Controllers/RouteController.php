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
 * The route of one standard in one work center sends each standard to its own page. The NOM-035 one is put together here
 * (board V2-Ruta-N035, and V3-AlDia once its six stages are done), with «Si hoy llegara una inspección» (V3-Inspeccion):
 * NOM-035 spans Campaigns, Responses, Results, Disclosure and Interventions, read through their contracts. An archived
 * center, a made-up one and another company's are a 404, as is a standard it does not know.
 */
final class RouteController extends Controller
{
    public function show(Tenant $tenant, string $workCenter, string $norm, WorkCenters $workCenters): RedirectResponse
    {
        $center = $workCenters->findActive($workCenter) ?? abort(404);

        return to_route(match ($norm) {
            'nom035' => 'tenant.nom035.route',
            'nom037' => 'tenant.telework.route',
            default => 'tenant.seating.route',
        }, [$tenant->slug, $center->id]);
    }

    public function nom035(Request $request, Tenant $tenant, string $workCenter, WorkCenters $workCenters, RoutePage $page): Response
    {
        $center = $workCenters->findActive($workCenter) ?? abort(404);

        return Inertia::render('tenancy/nom035-route', $page->props($tenant->slug, $center, seesScreenings: $request->user()?->can('view-trauma-screenings') ?? false));
    }

    public function inspection(Request $request, Tenant $tenant, string $workCenter, WorkCenters $workCenters, RoutePage $page): Response
    {
        $center = $workCenters->findActive($workCenter) ?? abort(404);

        return Inertia::render('tenancy/nom035-inspection', $page->inspection($tenant->slug, $center, seesScreenings: $request->user()?->can('view-trauma-screenings') ?? false));
    }
}
