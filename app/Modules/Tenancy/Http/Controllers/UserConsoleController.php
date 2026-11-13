<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Actions\SetSupportRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Actions\RemoveTwoFactor;
use App\Modules\Tenancy\Actions\SendPasswordReset;
use App\Modules\Tenancy\Support\UserPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The platform's users for its superadmin: find one, help them back in, and give or take away the support role. */
final class UserConsoleController extends Controller
{
    public function index(Request $request, UserPages $pages): Response
    {
        Gate::authorize('manage-platform-users');

        $search = trim($request->string('q')->toString());

        return Inertia::render('tenancy/users', [
            'users' => $pages->handle($search),
            'filters' => ['q' => $search],
        ]);
    }

    public function sendReset(User $user, SendPasswordReset $sendPasswordReset, Request $request): RedirectResponse
    {
        Gate::authorize('manage-platform-users');

        if (! $sendPasswordReset->handle($user, $request->user() ?? abort(401))) {
            return back()->withErrors(['user' => 'Ya se le envió un enlace hace un momento. Espera un minuto para enviar otro.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Enlace enviado a {$user->email}."]);

        return back();
    }

    public function removeTwoFactor(User $user, RemoveTwoFactor $removeTwoFactor, Request $request): RedirectResponse
    {
        Gate::authorize('manage-platform-users');
        // A superadmin's second factor is the platform's own lock: it is not taken off from the console, not even one's own.
        abort_if($user->isSuperadmin(), 403);

        $removeTwoFactor->handle($user, $request->user() ?? abort(401));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Verificación en dos pasos quitada a {$user->email}."]);

        return back();
    }

    public function grantSupport(User $user, SetSupportRole $setSupportRole): RedirectResponse
    {
        Gate::authorize('manage-platform-users');
        abort_if($user->isSuperadmin(), 403);

        $setSupportRole->grant($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->email} ahora es soporte."]);

        return back();
    }

    public function revokeSupport(User $user, SetSupportRole $setSupportRole): RedirectResponse
    {
        Gate::authorize('manage-platform-users');
        abort_if($user->isSuperadmin(), 403);

        $setSupportRole->revoke($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->email} ya no es soporte."]);

        return back();
    }
}
