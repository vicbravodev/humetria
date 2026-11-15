<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Http\Controllers;

use App\Models\User;
use App\Modules\FirstSteps\Actions\HideFirstSteps;
use App\Modules\FirstSteps\Actions\MarkWelcomed;
use App\Modules\FirstSteps\Actions\ShowFirstSteps;
use App\Modules\FirstSteps\Models\FirstStepsState;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class FirstStepsStateController
{
    public function welcome(#[CurrentUser] User $user, CurrentTenant $tenant, MarkWelcomed $action): RedirectResponse
    {
        Gate::authorize('manage', FirstStepsState::class);
        $action->handle($tenant->id(), $user->id);

        return back();
    }

    public function hide(#[CurrentUser] User $user, CurrentTenant $tenant, HideFirstSteps $action): RedirectResponse
    {
        Gate::authorize('manage', FirstStepsState::class);
        $action->handle($tenant->id(), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Puedes retomarlos desde tu menú de usuario.']);

        return back();
    }

    public function show(#[CurrentUser] User $user, CurrentTenant $tenant, ShowFirstSteps $action): RedirectResponse
    {
        Gate::authorize('manage', FirstStepsState::class);
        $action->handle($tenant->id(), $user->id);

        return back();
    }
}
