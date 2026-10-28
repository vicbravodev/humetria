<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Actions\AcceptInvitation;
use App\Modules\Tenancy\Enums\InvitationStatus;
use App\Modules\Tenancy\Exceptions\InvitationNoLongerValid;
use App\Modules\Tenancy\Http\Requests\AcceptInvitationRequest;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page reached from the invitation email. The token in the URL is the only credential, except for an email that
 * already has an account: that account must be the one signed in, so the link can never take it over.
 */
final class AcceptInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->pendingInvitation($token);
        $state = $invitation === null ? 'invalid' : $this->stateFor($invitation, $request->user());

        if ($state === 'sign_in') {
            // Fortify's login sends the account back here once it signs in.
            redirect()->setIntendedUrl(route('invitations.show', $token));
        }

        return Inertia::render('tenancy/accept-invitation', [
            'state' => $state,
            'trade_name' => $invitation?->tenant?->trade_name,
            'email' => $invitation?->email,
            'name' => $invitation?->name,
            'token' => $token,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->pendingInvitation($token);

        if ($invitation === null) {
            return redirect()->route('invitations.show', $token);
        }

        $user = $request->user();
        $state = $this->stateFor($invitation, $user);

        // Never accepts for another account, nor replaces its session with the invited one.
        abort_if($state === 'other_account', 403);

        // An existing account joins after signing in; a new password must never replace its own. The link asks it to
        // sign in and brings it back afterwards.
        if ($state === 'sign_in') {
            return redirect()->route('invitations.show', $token);
        }

        try {
            $user = $acceptInvitation->handle($invitation, $user ?? $request->toData());
        } catch (InvitationNoLongerValid) {
            return redirect()->route('invitations.show', $token);
        }

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Te damos la bienvenida a Humetría.']);

        return redirect()->route('dashboard');
    }

    /** Signs out whoever is signed in and comes back to the link, where the invited account can sign in. */
    public function switchAccount(Request $request, string $token): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('invitations.show', $token);
    }

    /**
     * @return 'confirm'|'other_account'|'sign_in'|'new_account'
     */
    private function stateFor(TenantInvitation $invitation, ?User $user): string
    {
        if ($user !== null) {
            return Str::lower($user->email) === $invitation->email ? 'confirm' : 'other_account';
        }

        return User::query()->whereRaw('lower(email) = ?', [$invitation->email])->exists() ? 'sign_in' : 'new_account';
    }

    private function pendingInvitation(string $token): ?TenantInvitation
    {
        $invitation = TenantInvitation::query()
            ->with('tenant')
            ->where('token_hash', TenantInvitation::hashToken($token))
            ->first();

        return $invitation?->status() === InvitationStatus::Pending ? $invitation : null;
    }
}
