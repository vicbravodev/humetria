<?php

declare(strict_types=1);

namespace App\Modules\Sso\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sso\Actions\SaveSsoConnection;
use App\Modules\Sso\Actions\SetSsoState;
use App\Modules\Sso\Actions\VerifySsoDomain;
use App\Modules\Sso\Http\Requests\SsoConnectionRequest;
use App\Modules\Sso\Http\Requests\SsoStateRequest;
use App\Modules\Sso\Models\SsoDomain;
use App\Modules\Sso\Support\SsoPage;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function verify(Request $request, CurrentTenant $tenant, #[RouteParameter('domain')] string $domain, VerifySsoDomain $verify): RedirectResponse
    {
        Gate::authorize('manage-members');
        $row = SsoDomain::query()->where('tenant_id', $tenant->id())->whereKey($domain)->firstOrFail();

        if (! $verify->handle($row)) {
            return to_route('tenant.sso.show', $request->route('tenant'))->withErrors(['domain' => "No encontramos el registro TXT de {$row->domain}. Los cambios de DNS pueden tardar un rato en llegar."]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Verificamos {$row->domain}."]);

        return to_route('tenant.sso.show', $request->route('tenant'));
    }

    public function state(SsoStateRequest $request, CurrentTenant $tenant, SetSsoState $set): RedirectResponse
    {
        $set->handle($tenant->id(), $request->boolean('enabled'), $request->boolean('enforced'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Actualizamos el inicio de sesión único.']);

        return to_route('tenant.sso.show', $request->route('tenant'));
    }
}
