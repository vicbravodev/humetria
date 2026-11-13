<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\Nom035Dossier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The printable NOM-035 dossier of one work center. `?ready=1` keeps only what the center can show (board V3-Inspeccion). A
 * center that is archived, made up or of another company is a 404.
 */
final class Nom035DossierController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, string $workCenter, WorkCenters $workCenters, Nom035Dossier $dossier): Response
    {
        $center = $workCenters->findActive($workCenter) ?? abort(404);

        return Inertia::render('tenancy/nom035-dossier', $dossier->build($tenant->slug, $center, $request->user()?->can('view-trauma-screenings') ?? false, $request->boolean('ready')));
    }
}
