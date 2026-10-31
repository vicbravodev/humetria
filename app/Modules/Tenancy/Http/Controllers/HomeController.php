<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Actions\ListUserTenants;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Where /admin leads: the console for the superadmin, the company's panel for a person with one, a list otherwise. */
final class HomeController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ListUserTenants $listUserTenants, CompanyRoles $roles): RedirectResponse|Response
    {
        if ($user->isSuperadmin()) {
            return redirect()->route('companies.index');
        }

        $companies = $listUserTenants->handle($user->id);

        if ($companies->total() === 1) {
            $company = $companies->getCollection()->sole();

            // The Comisión sees only NOM-037: its start is there, not a page that would answer 403.
            return redirect()->route($roles->of($user, $company) === TenantRole::Committee ? 'tenant.telework.index' : 'tenant.dashboard', $company);
        }

        return Inertia::render('tenancy/companies', [
            'companies' => $companies->through(fn (Tenant $company): array => [
                'slug' => $company->slug,
                'trade_name' => $company->trade_name,
                'status' => $company->status()->value,
                'status_label' => $company->status()->label(),
                'blocked' => $company->status()->isBlocked(),
            ]),
        ]);
    }
}
