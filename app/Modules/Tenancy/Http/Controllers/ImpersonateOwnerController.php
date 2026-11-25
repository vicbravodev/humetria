<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Impersonation;
use App\Models\User;
use App\Modules\Tenancy\Http\Requests\ImpersonateRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Context;

/**
 * "Entrar como owner" from the console: the superadmin works as the company's owner until "Salir". In the demo company
 * only, they may enter as its HR instead, to show what HR sees; a real company never offers that.
 */
final class ImpersonateOwnerController extends Controller
{
    public function __invoke(ImpersonateRequest $request, Tenant $tenant, #[CurrentUser] User $user, Impersonation $impersonation): RedirectResponse
    {
        $role = $request->role();

        // Never another member of the staff: that would hand over the console under someone else's name.
        $target = $tenant->membershipsWithRole($role)->with('user')->orderBy('id')->get()
            ->map(fn ($membership): ?User => $membership->user)
            ->first(fn (?User $member): bool => $member !== null && ! $member->isStaff());

        abort_if($target === null, 404);

        Context::scope(fn () => $impersonation->start($user, $target, $tenant->id, $request->reason(), $request->minutes()), ['tenant_id' => $tenant->id]);

        return redirect()->route('dashboard');
    }
}
