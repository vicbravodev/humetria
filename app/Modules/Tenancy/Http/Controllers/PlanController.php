<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PlanPage;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** «Plan y uso»: the owner reads which plan the company has, what it uses of its limits and which tools it has. Read only. */
final class PlanController extends Controller
{
    public function __invoke(Tenant $tenant, PlanPage $page): Response
    {
        Gate::authorize('manage-members');

        return Inertia::render('tenancy/plan', $page->handle($tenant));
    }
}
