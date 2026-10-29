<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Impersonation;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checked on every request while impersonating: once the superadmin behind it is gone or no
 * longer a superadmin, the session ends right there, not when they press "Salir".
 */
final class EndRevokedImpersonation
{
    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $impersonatorId = $this->impersonation->impersonatorId();

        if ($impersonatorId === null || User::query()->find($impersonatorId)?->hasRole(User::SUPERADMIN) === true) {
            return $next($request);
        }

        Log::warning('impersonation.revoked', [
            'user_id' => Auth::id(),
            'impersonator_id' => $impersonatorId,
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
