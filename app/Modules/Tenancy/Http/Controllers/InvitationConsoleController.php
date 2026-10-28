<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Actions\CancelInvitation;
use App\Modules\Tenancy\Actions\InviteOwner;
use App\Modules\Tenancy\Actions\ResendInvitation;
use App\Modules\Tenancy\Exceptions\InvitationNoLongerValid;
use App\Modules\Tenancy\Http\Requests\StoreInvitationRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InvitationConsoleController extends Controller
{
    public function store(StoreInvitationRequest $request, Tenant $tenant, InviteOwner $inviteOwner): RedirectResponse
    {
        $inviteOwner->handle($tenant, $request->toData(), $request->user() ?? abort(401));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitación enviada.']);

        return redirect()->route('companies.index');
    }

    public function resend(Tenant $tenant, TenantInvitation $invitation, ResendInvitation $resendInvitation): RedirectResponse
    {
        Gate::authorize('manage-tenants');

        try {
            $resendInvitation->handle($invitation, $this->admin());
        } catch (InvitationNoLongerValid) {
            return $this->noLongerOpen();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitación reenviada.']);

        return redirect()->route('companies.index');
    }

    public function cancel(Tenant $tenant, TenantInvitation $invitation, CancelInvitation $cancelInvitation): RedirectResponse
    {
        Gate::authorize('manage-tenants');

        try {
            $cancelInvitation->handle($invitation, $this->admin());
        } catch (InvitationNoLongerValid) {
            return $this->noLongerOpen();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitación cancelada.']);

        return redirect()->route('companies.index');
    }

    /** A console that showed the invitation before it was accepted or cancelled: say so where the list is. */
    private function noLongerOpen(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => 'Esta invitación ya no está abierta.']);

        return redirect()->route('companies.index');
    }

    private function admin(): User
    {
        return auth()->user() ?? abort(401);
    }
}
