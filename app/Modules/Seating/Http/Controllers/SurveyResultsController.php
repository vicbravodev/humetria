<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\SurveyPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The survey tab of a center: launch it and, once it is closed, read what it found with the minimum of people per group.
 * Only manage-seating (owner and hr): the viewer and the Comisión do not see it. Reading the results goes to the audit
 * trail, with the ids and no answer.
 */
final class SurveyResultsController extends Controller
{
    public function show(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, SurveyPages $pages, RecordAudit $audit): Response
    {
        Gate::authorize('manage-seating');
        $center = $access->find($request, $workCenter);
        $page = $pages->show($center, $request->string('area')->toString() ?: null);

        if ($page['results'] !== null && $page['survey'] !== null) {
            $audit->handle('seating.survey_results_viewed', null, [
                'campaign_id' => $page['survey']['id'],
                'work_center_id' => $center->id,
                'area_id' => $page['results']['area']['value'] ?? null,
            ]);
        }

        return Inertia::render('seating/survey', $page);
    }
}
