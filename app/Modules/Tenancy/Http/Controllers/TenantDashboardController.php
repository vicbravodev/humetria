<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Support\DashboardPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantDashboardController extends Controller
{
    public function __invoke(Request $request, DashboardPage $page): Response
    {
        return Inertia::render('dashboard', $page->props(
            $request->user()?->can('manage-campaigns') ?? false,
            $request->user()?->can('view-trauma-screenings') ?? false,
        ));
    }
}
