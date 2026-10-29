<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Impersonation;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;

/** "Entrar como owner" from the console: the superadmin works as the company's owner until "Salir". */
final class ImpersonateOwnerController extends Controller
{
    public function __invoke(Tenant $tenant, #[CurrentUser] User $user, Impersonation $impersonation): RedirectResponse
    {
        Gate::authorize('manage-tenants');

        $owner = $tenant->ownerMembership?->user;

        // Never another superadmin: that would hand over the console under someone else's name.
        abort_if($owner === null || $owner->isSuperadmin(), 404);

        Context::scope(fn () => $impersonation->start($user, $owner), ['tenant_id' => $tenant->id]);

        return redirect()->route('dashboard');
    }
}
