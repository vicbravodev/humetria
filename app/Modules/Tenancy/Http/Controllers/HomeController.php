<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Actions\ListUserTenants;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Where /admin leads: the console for the superadmin, the company's panel for a person with one, a list otherwise. */
final class HomeController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ListUserTenants $listUserTenants): RedirectResponse|Response
    {
        if ($user->hasRole(User::SUPERADMIN)) {
            return redirect()->route('companies.index');
        }

        $companies = $listUserTenants->handle($user->id);

        if ($companies->count() === 1) {
            return redirect()->route('tenant.dashboard', $companies->sole());
        }

        return Inertia::render('tenancy/companies', [
            'companies' => $companies->map(fn (Tenant $company): array => [
                'slug' => $company->slug,
                'trade_name' => $company->trade_name,
                'status' => $company->status()->value,
                'status_label' => $company->status()->label(),
                'blocked' => $company->status()->isBlocked(),
            ])->all(),
        ]);
    }
}
