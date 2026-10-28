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
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page a guest reaches from the invitation email. The token in the URL is the only credential.
 */
final class AcceptInvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = $this->pendingInvitation($token);

        return Inertia::render('tenancy/accept-invitation', [
            'state' => $invitation === null ? 'invalid' : 'new_account',
            'trade_name' => $invitation?->tenant?->trade_name,
            'email' => $invitation?->email,
            'name' => $invitation?->name,
            'token' => $token,
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->pendingInvitation($token);

        if ($invitation === null) {
            return redirect()->route('invitations.show', $token);
        }

        // An existing account joins after signing in; a new password must never replace its own.
        if (User::query()->whereRaw('lower(email) = ?', [$invitation->email])->exists()) {
            return redirect()->route('login');
        }

        try {
            $user = $acceptInvitation->handle($invitation, $request->toData());
        } catch (InvitationNoLongerValid) {
            return redirect()->route('invitations.show', $token);
        }

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bienvenido a Humetría.']);

        return redirect()->route('dashboard');
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
