<?php

declare(strict_types=1);

namespace App\Modules\Sso\Http\Middleware;

use App\Models\User;
use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A company that enforces single sign-on lets in only people who signed in through its provider in this session: a password, a
 * second factor or a passkey open the account, never that company. The owner is the way out when the provider fails, so the
 * owner is not asked. It only counts while the plan still has the module: without it nobody could sign in, so nothing is enforced.
 * Runs after ResolveTenant, which sets the company, and its roles, for the person.
 */
final readonly class RequireSsoWhenEnforced
{
    public function __construct(private CurrentTenant $tenant, private CompanyModules $modules, private CompanyRegistry $companies) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = $this->tenant->id();

        if (! $user instanceof User || ! $this->enforced($tenantId) || $user->can('manage-members') || $this->signedIn($request, $user, $tenantId)) {
            return $next($request);
        }

        $slug = $this->companies->find($tenantId)->slug ?? '';

        return Inertia::location(route('sso.start', $slug));
    }

    private function enforced(string $tenantId): bool
    {
        return $this->modules->enabled($tenantId, 'sso')
            && SsoConnection::query()->where('tenant_id', $tenantId)->where('enforced', true)->where('enabled', true)->exists();
    }

    private function signedIn(Request $request, User $user, string $tenantId): bool
    {
        $tenants = $request->session()->get('sso.tenants');

        return $request->session()->get('sso.user_id') === $user->id && is_array($tenants) && in_array($tenantId, $tenants, true);
    }
}
