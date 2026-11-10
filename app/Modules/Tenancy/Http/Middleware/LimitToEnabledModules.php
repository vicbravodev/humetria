<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\ModuleRoutes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A company without a module gets the same 404 on its routes as for a page that does not exist, whatever ids the URL carries.
 * Nothing is deleted: its data stays, and the pages come back if the module does. Runs after ResolveTenant, which has bound the
 * company; a union has its own limiter (LimitUnionToCompensation) and the modules do not apply to it.
 */
final class LimitToEnabledModules
{
    public function __construct(private readonly CompanyModules $modules) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant || $tenant->isUnion()) {
            return $next($request);
        }

        foreach (ModuleRoutes::patterns() as $module => $patterns) {
            if ($request->routeIs(...$patterns)) {
                abort_unless($this->modules->enabled($tenant->id, $module), 404);

                break;
            }
        }

        return $next($request);
    }
}
