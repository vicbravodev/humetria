<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Inertia;
use Inertia\Response;

final class TenantDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            // Every evaluation hangs from a work center: until there is one, the panel points there first.
            'hasWorkCenters' => WorkCenter::query()->whereNull('archived_at')->exists(),
        ]);
    }
}
