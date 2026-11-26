<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\RemoveTenantLogo;
use App\Modules\Tenancy\Actions\SetTenantBrandColor;
use App\Modules\Tenancy\Actions\SetTenantLogo;
use App\Modules\Tenancy\Http\Requests\UpdateTenantBrandRequest;
use App\Modules\Tenancy\Http\Requests\UpdateTenantLogoRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** The superadmin gives a company its logo and its accent (white label), or takes them off. */
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

    public function brand(UpdateTenantBrandRequest $request, Tenant $tenant, SetTenantLogo $setLogo, SetTenantBrandColor $setColor): RedirectResponse
    {
        if (($logoPath = $request->logoPath()) !== null) {
            $setLogo->handle($tenant, $logoPath);
        }

        if (($darkPath = $request->darkLogoPath()) !== null) {
            $setLogo->handle($tenant, $darkPath, dark: true);
        }

        $setColor->handle($tenant, $request->color());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marca guardada.']);

        return redirect()->route('companies.index');
    }

    public function destroyDark(Tenant $tenant, RemoveTenantLogo $removeLogo): RedirectResponse
    {
        Gate::authorize('manage-tenants');

        $removeLogo->handle($tenant, onlyDark: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo para modo oscuro quitado.']);

        return redirect()->route('companies.index');
    }
}
