<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Support\PlatformOverview;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** «Resumen» of the platform for its superadmin: a few figures, each a way into the list behind it. */
final class PlatformOverviewController extends Controller
{
    public function __invoke(PlatformOverview $overview): Response
    {
        Gate::authorize('view-platform-overview');

        return Inertia::render('tenancy/overview', $overview->handle());
    }
}
