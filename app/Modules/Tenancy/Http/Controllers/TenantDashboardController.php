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
        // A union has no compliance panel; its start is Compensación.
        if ($tenant->isUnion()) {
            return to_route('tenant.compensation.index', $tenant);
        }

        $user = $request->user();
        // The route sits behind the auth middleware: the check only narrows the type.
        abort_if($user === null, 403);

        // Whoever does not land on the panel (StartRoute) is sent where they do: the same rule, so it never loops. The
        // permissions of the company are already loaded by the permission of the route.
        if (! $user->can(StartRoute::PANEL_ALSO_NEEDS)) {
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
