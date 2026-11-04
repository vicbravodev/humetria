<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Complaints\Actions\RouteUnassignedComplaints;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** The complaints nobody holds: whoever manages the channel asks to assign them again, and learns only how many were. */
final class UnassignedController extends Controller
{
    public function route(mixed $tenant, RouteUnassignedComplaints $route, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('manage-complaint-channel');
        $made = $route->handle($user->id);
        Inertia::flash('toast', ['type' => $made > 0 ? 'success' : 'info', 'message' => $made > 0 ? 'Los casos sin encargada se asignaron.' : 'No hay a quién asignarlos: revisa a quién atiende cada opción.']);

        return to_route('tenant.complaints.channel.show', $tenant);
    }
}
