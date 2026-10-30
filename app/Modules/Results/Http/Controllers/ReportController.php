<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Results\Actions\SaveReportDetails;
use App\Modules\Results\Http\Requests\ReportDetailsRequest;
use App\Modules\Results\Support\ReportBuilder;
use App\Modules\Results\Support\RiskLevels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The 7.7 report of a closed evaluation. Every role reads it; those who manage campaigns fill in its parts. A campaign
 * with no report (open, a Guía I campaign, another company's) is not found.
 */
final class ReportController extends Controller
{
    public function __construct(private readonly CampaignRecords $campaigns) {}

    public function show(Request $request, mixed $tenant, string $campaign, ReportBuilder $builder, RecordAudit $audit): Response
    {
        [$found, $guide] = $this->find($campaign);
        $audit->handle('results.report_viewed', metadata: ['campaign_id' => $found->id]);
        $report = $builder->build($found, $guide);
        $canEdit = $request->user()?->can('manage-campaigns') ?? false;

        return Inertia::render('results/report', [
            'campaignId' => $found->id,
            'report' => $report,
            'levels' => RiskLevels::catalog(),
            'canEdit' => $canEdit,
            'form' => $canEdit ? ReportBuilder::form($report) : null,
        ]);
    }

    public function update(ReportDetailsRequest $request, mixed $tenant, string $campaign, SaveReportDetails $save): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        [$found] = $this->find($campaign);
        $user = $request->user();
        abort_if($user === null, 403);

        $save->handle($found->id, $request->details(), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Informe guardado.']);

        return redirect()->route('tenant.campaigns.report', [$tenant, $found->id]);
    }

    /**
     * @return array{CampaignRecord, string}
     */
    private function find(string $id): array
    {
        $campaign = $this->campaigns->find($id);
        $guide = $campaign === null ? null : ReportBuilder::guideOf($campaign);

        return $campaign !== null && $guide !== null ? [$campaign, $guide] : abort(404);
    }
}
