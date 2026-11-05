<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Support\DossierPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The printable dossier of the disclosure of a work center (num. 10.2 and 10.4). Owner and RH only; seeing it is audited. A center
 * of another company, an archived one or one that does not exist is not found. `?ready=1` keeps only what the center meets
 * (board V3-Inspeccion, «Solo lo que tienes»).
 */
final class DossierController extends Controller
{
    public function show(Request $request, mixed $tenant, string $workCenter, DossierPages $pages): Response
    {
        Gate::authorize('manage-disclosure');

        return Inertia::render('disclosure/dossier', $pages->show($workCenter, $request->boolean('ready')));
    }
}
