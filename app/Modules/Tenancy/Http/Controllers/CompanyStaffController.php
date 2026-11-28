<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Actions\AddTenantNote;
use App\Modules\Tenancy\Actions\RestrictTenant;
use App\Modules\Tenancy\Http\Requests\RiskRestrictionRequest;
use App\Modules\Tenancy\Http\Requests\StoreTenantNoteRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** What the staff does from a company's page: write a note about it, and, the superadmin, stop it from the risk zone. */
final class CompanyStaffController extends Controller
{
    public function storeNote(StoreTenantNoteRequest $request, Tenant $tenant, #[CurrentUser] User $user, AddTenantNote $addNote): RedirectResponse
    {
        $addNote->handle($tenant, $user, $request->body());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nota guardada.']);

        return redirect()->route('companies.show', $tenant);
    }

    public function restrict(RiskRestrictionRequest $request, Tenant $tenant, RestrictTenant $restrictTenant): RedirectResponse
    {
        $restriction = $request->restriction();
        abort_if($tenant->restriction === $restriction, 409);

        $restrictTenant->handle($tenant, $restriction, $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => $restriction->confirmation()]);

        return redirect()->route('companies.show', $tenant);
    }
}
