<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\InviteOwner;
use App\Modules\Tenancy\Http\Requests\StoreInvitationRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class InvitationConsoleController extends Controller
{
    public function store(StoreInvitationRequest $request, Tenant $tenant, InviteOwner $inviteOwner): RedirectResponse
    {
        $inviteOwner->handle($tenant, $request->toData(), $request->user() ?? abort(401));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitación enviada.']);

        return redirect()->route('companies.index');
    }
}
