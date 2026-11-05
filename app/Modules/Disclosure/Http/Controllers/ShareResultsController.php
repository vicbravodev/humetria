<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Disclosure\Support\SharePages;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Comparte los resultados con tu equipo» of one closed evaluation (board V2-N035-Compartir; num. 7.8). Owner and RH only;
 * an evaluation of another company, one that does not exist or one of an archived center is not found.
 */
final class ShareResultsController extends Controller
{
    public function show(mixed $tenant, string $campaign, CampaignRecords $campaigns, WorkCenters $workCenters, SharePages $pages): Response
    {
        Gate::authorize('manage-disclosure');
        $found = $campaigns->find($campaign) ?? abort(404);
        abort_unless($found->closed, 404);
        $center = $workCenters->findActive($found->workCenterId) ?? abort(404);

        return Inertia::render('disclosure/share-results', $pages->show($found, $center));
    }
}
