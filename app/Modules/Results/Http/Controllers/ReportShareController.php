<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Results\Actions\CreateReportShare;
use App\Modules\Results\Actions\RevokeReportShare;
use App\Modules\Results\Http\Requests\ShareRequest;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Results\Support\ReportBuilder;
use App\Modules\Results\Support\ResultsClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Temporary links to an evaluation's report, opened and revoked by those who manage campaigns. A new link is flashed
 * once, in the clear: only its hash stays.
 */
final class ReportShareController extends Controller
{
    public function store(ShareRequest $request, mixed $tenant, string $campaign, CampaignRecords $campaigns, CreateReportShare $create): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $found = $campaigns->find($campaign);
        abort_if($found === null || ReportBuilder::guideOf($found) === null, 404);
        $user = $request->user();
        abort_if($user === null, 403);

        [$share, $token] = $create->handle($found->id, $request->duration(), $user->id);
        Inertia::flash('reportLink', ['url' => route('report.shared', $token), 'expiresAt' => ResultsClock::moment($share->expires_at)]);

        return redirect()->route('tenant.campaigns.report', [$tenant, $found->id]);
    }

    public function destroy(mixed $tenant, string $campaign, string $share, RevokeReportShare $revoke): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $found = Str::isUuid($campaign) ? ReportShare::query()->where('campaign_id', $campaign)->find($share) : null;
        abort_if($found === null, 404);

        $revoke->handle($found);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace revocado.']);

        return redirect()->route('tenant.campaigns.report', [$tenant, $campaign]);
    }
}
