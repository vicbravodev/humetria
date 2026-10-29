<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Where /admin leads: the console for the superadmin, the company's panel for a person with one, a list otherwise. */
final class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $request->user();

        if ($user?->hasRole(User::SUPERADMIN)) {
            return redirect()->route('companies.index');
        }

        $companies = Tenant::query()
            ->whereIn('id', TenantMembership::query()->where('user_id', $user?->getAuthIdentifier())->select('tenant_id'))
            ->orderBy('trade_name')
            ->get();

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
