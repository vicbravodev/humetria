<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\SetGroupMinimum;
use App\Modules\Tenancy\Contracts\GroupMinimums;
use App\Modules\Tenancy\Http\Requests\GroupMinimumRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Privacidad de los resultados» (the owner's): the minimum of people a group needs before its result is shown, for every
 * tool with anonymous answers (NOM-035, Ley Silla, clima y pulso, denuncias), so it lives with the company, not one tool.
 */
final class GroupMinimumController extends Controller
{
    public function edit(GroupMinimums $minimums): Response
    {
        Gate::authorize('manage-members');

        return Inertia::render('tenancy/privacy', [
            'minimum' => $minimums->current(),
            'chosen' => $minimums->own(),
            'recommended' => $minimums->platformDefault(),
            'lowest' => GroupMinimums::LOWEST,
            'highest' => GroupMinimums::HIGHEST,
        ]);
    }

    public function update(GroupMinimumRequest $request, Tenant $tenant, SetGroupMinimum $setGroupMinimum): RedirectResponse
    {
        $setGroupMinimum->handle($tenant, $request->minimum());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mínimo por grupo guardado.']);

        return redirect()->route('tenant.privacy.edit', $tenant);
    }
}
