<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\RemoveTenantLogo;
use App\Modules\Tenancy\Actions\SetTenantLogo;
use App\Modules\Tenancy\Http\Requests\UpdateTenantLogoRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** The superadmin gives a company the logo its people see on their emails, or takes it off. */
final class TenantLogoController extends Controller
{
    public function update(UpdateTenantLogoRequest $request, Tenant $tenant, SetTenantLogo $setLogo): RedirectResponse
    {
        $setLogo->handle($tenant, $request->logoPath());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo guardado.']);

        return redirect()->route('companies.index');
    }

    public function destroy(Tenant $tenant, RemoveTenantLogo $removeLogo): RedirectResponse
    {
        Gate::authorize('manage-tenants');

        $removeLogo->handle($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo quitado.']);

        return redirect()->route('companies.index');
    }
}
