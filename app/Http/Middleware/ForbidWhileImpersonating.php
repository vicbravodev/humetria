<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The superadmin works as the owner, not as their login: while impersonating, the owner's own account
 * (profile, password, two-factor, passkeys) answers 404, as if it were not there. Fortify registers
 * its routes itself, so this guards them by name instead of per route.
 */
final class ForbidWhileImpersonating
{
    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * Names of the owner's account routes, and of the routes that open a complaint: not even a superadmin acting as the owner
     * reads a case (criterio C-44). Every route under admin/user/ must be here: a test enumerates them. A name may end in *.
     *
     * @return list<string>
     */
    public static function protectedRoutes(): array
    {
        return [
            'profile.edit', 'profile.update', 'profile.destroy',
            'security.edit', 'user-password.update',
            'password.confirm', 'password.confirm.store', 'password.confirmation',
            'two-factor.confirm', 'two-factor.enable', 'two-factor.disable', 'two-factor.qr-code',
            'two-factor.recovery-codes', 'two-factor.regenerate-recovery-codes', 'two-factor.secret-key',
            'passkey.store', 'passkey.registration-options', 'passkey.destroy',
            'tenant.complaints.index', 'tenant.complaints.cases.*', 'tenant.complaints.procedure',
            // What is paid and by whom is the staff's, never the owner's: nothing of billing is reached while acting as one.
            'billing.*',
        ];
    }

    /** Whether a route is one of those guarded by name. */
    public static function guards(string $routeName): bool
    {
        return Str::is(self::protectedRoutes(), $routeName);
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->impersonation->isActive() && $request->routeIs(...self::protectedRoutes()), 404);

        return $next($request);
    }
}
