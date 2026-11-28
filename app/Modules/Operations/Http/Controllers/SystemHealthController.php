<?php

declare(strict_types=1);

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Support\SystemHealth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** «Salud del sistema», for the superadmin: the queues, the scheduler, the webhooks and the messages of the day. */
final class SystemHealthController extends Controller
{
    public function __invoke(SystemHealth $health): Response
    {
        Gate::authorize('view-operations');

        return Inertia::render('operations/health', $health->handle());
    }
}
