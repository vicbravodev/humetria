<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\ComplianceRoutes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A union account has no compliance tools: on their routes it gets the same 404 as for a page that does not exist,
 * whatever ids the URL carries, so nothing reveals them. Runs after ResolveTenant, which has bound the account.
 */
final class LimitUnionToCompensation
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        abort_if($tenant instanceof Tenant && $tenant->isUnion() && $request->routeIs(...ComplianceRoutes::patterns()), 404);

        return $next($request);
    }
}
