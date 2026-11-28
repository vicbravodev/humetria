<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Impersonation;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * "Salir" from the impersonation banner: back to the console as the superadmin. Leaving the demo company, whoever runs the
 * demo goes back to its room instead («Volver a la sala» of the guide), where the session ends.
 */
final class StopImpersonationController extends Controller
{
    public function __invoke(Impersonation $impersonation, DemoCompanies $demos): RedirectResponse
    {
        abort_unless($impersonation->isActive(), 404);

        $tenantId = $impersonation->tenantId();
        // Signed out instead (no longer a superadmin), the console's auth sends them on to login.
        $user = $impersonation->stop();

        if ($user !== null && $tenantId !== null && $demos->isDemo($tenantId) && Gate::forUser($user)->allows('manage-demo')) {
            return redirect()->route('demo-room.show');
        }

        return redirect()->route('companies.index');
    }
}
