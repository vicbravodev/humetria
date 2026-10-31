<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Enums\TenantRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Comisión de seguridad e higiene sees nothing of the company outside NOM-037 (criterio C-25): inside a company's
 * routes, its members get 403 anywhere but tenant.telework.*. The account pages (settings.*, logout) and the company
 * switcher live outside that group, so they stay open. Runs after ResolveTenant, which has set the company as the
 * team of Spatie's roles.
 */
final class LimitCommitteeToTelework
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole(TenantRole::Committee->value) !== true || $request->routeIs('tenant.telework.*')) {
            return $next($request);
        }

        // The panel is the one page it may be sent to by habit: its start is NOM-037. Everything else is closed.
        abort_unless($request->routeIs('tenant.dashboard'), 403);

        return redirect()->route('tenant.telework.index', $request->route('tenant'));
    }
}
