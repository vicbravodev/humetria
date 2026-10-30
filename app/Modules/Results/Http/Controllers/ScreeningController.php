<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Responses\Data\ScreeningRecord;
use App\Modules\Results\Support\ResultsClock;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who requires a clinical assessment after their Guía I (num. 5.5), to channel them to care, and their names for the
 * record of num. 5.8 c. Only for whoever may see the Guía I; for anyone else it does not exist (404).
 */
final class ScreeningController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request, mixed $tenant, string $campaign, CampaignRecords $campaigns, ResponseReader $responses, Roster $roster, WorkCenters $workCenters, RecordAudit $audit): Response
    {
        abort_unless($request->user()?->can('view-trauma-screenings') ?? false, 404);
        $found = $campaigns->find($campaign) ?? abort(404);
        $audit->handle('results.screenings_viewed', metadata: ['campaign_id' => $found->id]);

        $page = $responses->requiringAssessment($found->id, self::PER_PAGE);
        $people = $roster->findMany(array_values(array_map(fn (ScreeningRecord $record): string => $record->employeeId, $page->items())));

        return Inertia::render('results/screenings', [
            'campaign' => ['id' => $found->id, 'workCenter' => $workCenters->profile($found->workCenterId)->name ?? '', 'kindLabel' => $found->kindLabel],
            'counts' => $responses->screeningCounts($found->id),
            'people' => $page->through(fn (ScreeningRecord $record): array => [
                'id' => $record->employeeId,
                'name' => $people[$record->employeeId]->name ?? '',
                'area' => $people[$record->employeeId]->areaName ?? '',
                'answeredOn' => ResultsClock::day($record->answeredAt),
            ]),
        ]);
    }
}
