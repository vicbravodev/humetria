<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Impersonation;
use Illuminate\Http\RedirectResponse;

/** "Salir" from the impersonation banner: back to the console as the superadmin. */
final class StopImpersonationController extends Controller
{
    public function __invoke(Impersonation $impersonation): RedirectResponse
    {
        abort_unless($impersonation->isActive(), 404);

        // Signed out instead (no longer a superadmin), the console's auth sends them on to login.
        $impersonation->stop();

        return redirect()->route('companies.index');
    }
}
