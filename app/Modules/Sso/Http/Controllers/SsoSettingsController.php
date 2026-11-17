<?php

declare(strict_types=1);

namespace App\Modules\Sso\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sso\Actions\SaveSsoConnection;
use App\Modules\Sso\Http\Requests\SsoConnectionRequest;
use App\Modules\Sso\Support\SsoPage;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The owner's page of single sign-on: the identity provider, the domains and, when ready, switching it on. */
final class SsoSettingsController extends Controller
{
    public function show(CurrentTenant $tenant, SsoPage $page): Response
    {
        Gate::authorize('manage-members');

        return Inertia::render('sso/settings', $page->handle($tenant->id()));
    }

    public function update(SsoConnectionRequest $request, CurrentTenant $tenant, SaveSsoConnection $save): RedirectResponse
    {
        $save->handle($tenant->id(), $request->toData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guardamos la configuración de SSO.']);

        return to_route('tenant.sso.show', $request->route('tenant'));
    }
}
