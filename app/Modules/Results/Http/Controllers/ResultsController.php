<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Results\Support\ResultsPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The results of a campaign of the company in the URL. Every role sees them; the Guía I only those who channel people.
 */
final class ResultsController extends Controller
{
    public function __construct(private readonly CampaignRecords $campaigns, private readonly RecordAudit $audit) {}

    public function show(Request $request, mixed $tenant, string $campaign, ResultsPages $pages): Response
    {
        $found = $this->find($campaign);
        $this->audit->handle('results.viewed', metadata: ['campaign_id' => $found->id]);
        $area = $request->string('area')->toString();

        return Inertia::render('results/show', $pages->show($found, $area === '' ? null : $area, $request->user()?->can('view-trauma-screenings') ?? false));
    }

    /** Another company's campaign is not found, the same 404 as one that does not exist. */
    private function find(string $id): CampaignRecord
    {
        return $this->campaigns->find($id) ?? abort(404);
    }
}
