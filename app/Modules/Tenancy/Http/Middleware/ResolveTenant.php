<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Actions\ListUserTenants;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds a request to the company named in its URL. Only members get in: anyone else, a superadmin
 * included, gets the same 404 as for a company that does not exist, so the URL reveals nothing.
 * Membership is checked directly, not through a Policy, because Gate::before lets a superadmin pass.
 */
final class ResolveTenant
{
    public function __construct(private readonly ListUserTenants $listUserTenants) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');
        $userId = $request->user()?->id;

        // Implicit route binding only runs when the action type-hints the model; this middleware must not depend on that.
        $tenant = is_string($slug) ? Tenant::query()->where('slug', $slug)->first() : $slug;

        abort_unless(
            $tenant instanceof Tenant
            && $userId !== null
            && TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $userId)->exists(),
            404,
        );

        $request->route()?->setParameter('tenant', $tenant);
        Context::add('tenant_id', $tenant->id);

        $status = $tenant->status();

        Inertia::share('tenant', [
            'slug' => $tenant->slug,
            'trade_name' => $tenant->trade_name,
            'status' => $status->value,
            'blocked' => $status->isBlocked(),
            'trial_days_left' => null,
        ]);
        Inertia::share('tenants', fn (): array => $this->listUserTenants->handle($userId)
            ->map(fn (Tenant $company): array => ['slug' => $company->slug, 'trade_name' => $company->trade_name])
            ->all());

        return $next($request);
    }
}
