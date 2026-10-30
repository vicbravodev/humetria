<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Actions\ListUserTenants;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use App\Modules\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds a request to the company named in its URL. Only members get in: anyone else, a superadmin
 * included, gets the same 404 as for a company that does not exist, so the URL reveals nothing.
 * Membership is checked directly, not through a Policy, because Gate::before lets a superadmin pass.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly ListUserTenants $listUserTenants,
        private readonly TenantContext $tenantContext,
        private readonly CompanyRoles $companyRoles,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        // Implicit route binding only runs when the action type-hints the model; this middleware must not depend on that.
        $tenant = is_string($slug) ? Tenant::query()->where('slug', $slug)->first() : $slug;

        $user = $request->user();

        abort_unless(
            $tenant instanceof Tenant
            && $user !== null
            && TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists(),
            404,
        );

        $request->route()?->setParameter('tenant', $tenant);
        // Also tags every log line, audit entry and queued job with the company, and makes it Spatie's team.
        $this->tenantContext->set($tenant);
        // Roles read before (as the superadmin's, with no team) must be read again inside the company.
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $role = $this->companyRoles->of($user, $tenant);

        // A membership without a role is broken data: nothing is shown rather than guessing.
        abort_if($role === null, 404);

        $status = $tenant->status();

        Inertia::share('tenant', [
            'slug' => $tenant->slug,
            'trade_name' => $tenant->trade_name,
            'status' => $status->value,
            'blocked' => $status->isBlocked(),
            'trial_days_left' => $tenant->trialDaysLeft(),
            'role' => $role->value,
            'role_label' => $role->label(),
            'can' => [
                'manage_organization' => $user->can('manage-organization'),
                'manage_members' => $user->can('manage-members'),
                'manage_campaigns' => $user->can('manage-campaigns'),
            ],
        ]);
        Inertia::share('tenants', fn (): array => $this->listUserTenants->handle($user->id)
            ->map(fn (Tenant $company): array => ['slug' => $company->slug, 'trade_name' => $company->trade_name])
            ->all());

        return $next($request);
    }
}
