<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\DashboardPage;
use App\Modules\Tenancy\Support\StartRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantDashboardController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, DashboardPage $page, CompanyModules $modules, StartRoute $startRoute): Response|RedirectResponse
    {
        $user = $request->user();
        // The route sits behind the auth middleware: the check only narrows the type.
        abort_if($user === null, 403);

        // A union has no compliance panel: its start is Compensación for whoever sees it, else where StartRoute says.
        if ($tenant->isUnion()) {
            return to_route($startRoute->for($user, $tenant), $tenant);
        }

        // Whoever does not land on the panel (StartRoute) is sent where they do, never shown a 403: the same rule (both
        // permissions), so it never loops. The route is exempt from RequireRoutePermission for this.
        if (! array_all(StartRoute::PANEL_NEEDS, fn (string $permission): bool => $user->can($permission))) {
            return to_route($startRoute->for($user, $tenant), $tenant);
        }

        // The standards of the panel the person may see, of those the company has.
        $norms = array_keys(array_filter(
            ['nom035' => 'view-nom035-route', 'nom037' => 'view-telework', 'seating' => 'view-seating'],
            fn (string $permission, string $norm): bool => $user->can($permission) && $modules->enabled($tenant->id, $norm),
            ARRAY_FILTER_USE_BOTH,
        ));

        return Inertia::render('dashboard', $page->props(
            $tenant->slug,
            $user->id,
            $norms,
            seesScreenings: $user->can('view-trauma-screenings'),
            reviewsNotices: $user->can('review-workplaces'),
            handlesComplaints: $user->can('handle-complaints'),
            managesVacations: $user->can('manage-vacations') && $modules->enabled($tenant->id, 'vacations'),
        ));
    }
}
