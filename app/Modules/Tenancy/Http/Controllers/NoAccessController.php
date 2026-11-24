<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where a person lands who may open nothing in the company yet: it says so and whom to ask, the owner by name. It asks for
 * no permission and never sends anyone on, so it cannot loop.
 */
final class NoAccessController extends Controller
{
    public function __invoke(Tenant $tenant): Response
    {
        return Inertia::render('tenancy/no-access', [
            'ownerName' => $tenant->ownerMembership()->with('user:id,name')->first()?->user?->name,
        ]);
    }
}
