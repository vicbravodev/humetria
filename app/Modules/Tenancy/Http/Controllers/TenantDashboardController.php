<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\DashboardPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantDashboardController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, DashboardPage $page): Response|RedirectResponse
    {
        // A union has no compliance panel; its start is Compensación.
        if ($tenant->isUnion()) {
            return to_route('tenant.compensation.index', $tenant);
        }

        return Inertia::render('dashboard', $page->props(
            $request->user()?->can('manage-campaigns') ?? false,
            $request->user()?->can('view-trauma-screenings') ?? false,
        ));
    }
}
