<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\DashboardPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantDashboardController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, DashboardPage $page, CompanyModules $modules): Response|RedirectResponse
    {
        // A union has no compliance panel; its start is Compensación.
        if ($tenant->isUnion()) {
            return to_route('tenant.compensation.index', $tenant);
        }

        $user = $request->user();
        // The route sits behind the auth middleware: the check only narrows the type.
        abort_if($user === null, 403);

        // The Comisión and the handlers of complaints start in their own tool: the panel sends them there. The roles of the
        // company are already loaded by the permission of the route.
        foreach ([TenantRole::Committee, TenantRole::Complaints] as $restricted) {
            if ($user->hasRole($restricted->value)) {
                return to_route($restricted->startRoute(), $tenant);
            }
        }

        return Inertia::render('dashboard', $page->props(
            $tenant->slug,
            $user->id,
            seesScreenings: $user->can('view-trauma-screenings'),
            reviewsNotices: $user->can('review-workplaces'),
            handlesComplaints: $user->can('handle-complaints'),
            managesVacations: $user->can('manage-vacations') && $modules->enabled($tenant->id, 'vacations'),
        ));
    }
}
