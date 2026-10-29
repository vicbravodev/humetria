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
 * Checked on every request that carries the impersonation mark: once the superadmin behind it is
 * gone or no longer a superadmin, or the mark is not the signed-in user's, the session ends right
 * there, not when someone presses "Salir".
 */
final class EndRevokedImpersonation
{
    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mark = $request->session()->get(Impersonation::SESSION_KEY);

        if ($mark === null) {
            return $next($request);
        }

        // Null too when the mark is not this user's: a guest, or someone who signed in over a leftover mark.
        $impersonatorId = $this->impersonation->impersonatorId();

        if ($impersonatorId !== null && User::query()->find($impersonatorId)?->hasRole(User::SUPERADMIN) === true) {
            return $next($request);
        }

        Log::warning('impersonation.revoked', [
            'user_id' => $request->user()?->getAuthIdentifier(),
            'impersonator_id' => $mark,
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
