<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Support\RoutePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every company route asks for its permission (RoutePermissions): the person's role in the company plus their own
 * permissions. Without it, 403 (404 where the page must not be known to exist); the few exempt routes ask for none. Runs after ResolveTenant, which has set the company as the team of Spatie's roles, and after the union and module limits, so a page the company does not have answers their 404 to anyone.
 */
final class RequireRoutePermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName() ?? '';

        if (RoutePermissions::isExempt($name)) {
            return $next($request);
        }

        $permission = RoutePermissions::for($name);

        // A company route missing from the map is closed to everyone: never open by default (RoutePermissionCoverageTest).
        abort_if($permission === null, 403);
        abort_unless($request->user()?->can($permission) === true, RoutePermissions::refusalStatus($name));

        return $next($request);
    }
}
