<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Enums\TenantRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A restricted role sees nothing of the company outside its own tools: the Comisión de seguridad e higiene only NOM-037,
 * Ley Silla and the complaints channel (criterio C-25), the handlers of complaints only the channel. Inside a company's
 * routes, their members get 403 anywhere else. The account pages (settings.*, logout) and the company switcher live outside
 * that group, so they stay open. Runs after ResolveTenant, which has set the company as the team of Spatie's roles.
 */
final class LimitRestrictedRoles
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach (TenantRole::cases() as $role) {
            $allowed = $role->allowedRoutes();

            if ($allowed === null || $request->user()?->hasRole($role->value) !== true || $request->routeIs(...$allowed)) {
                continue;
            }

            // The panel is the one page it may be sent to by habit: its start is its own tool. Everything else is closed.
            abort_unless($request->routeIs('tenant.dashboard'), 403);

            return redirect()->route($role->startRoute(), $request->route('tenant'));
        }

        return $next($request);
    }
}
