<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Results\Support\ReportBuilder;
use App\Modules\Results\Support\ResultsClock;
use App\Modules\Results\Support\RiskLevels;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * An evaluation's report opened from a temporary link, with no account: the token is the credential. It shows the
 * report and nothing of the Guía I. A link unknown, expired, revoked, or of a blocked company opens the same page that
 * names no company, as a 404. Each opening goes to the audit trail. Never indexed, never cached.
 */
final class SharedReportController extends Controller
{
    public function show(Request $request, string $token, Companies $companies, CampaignRecords $campaigns, ReportBuilder $builder, RecordAudit $audit): Response
    {
        // Read past the company scope: until the link is known, no company is.
        $share = ReportShare::query()->withoutGlobalScopes()->open()->where('token_hash', ReportShare::hashToken($token))->first();
        $company = $share === null ? null : $companies->enter($share->tenant_id);
        $campaign = $share === null || $company === null || $company->blocked ? null : $campaigns->find($share->campaign_id);
        $guide = $campaign === null ? null : ReportBuilder::guideOf($campaign);

        if ($share === null || $company === null || $campaign === null || $guide === null) {
            return self::private(Inertia::render('results/shared-report', ['company' => null, 'expiresAt' => null, 'report' => null, 'levels' => []])->toResponse($request)->setStatusCode(404));
        }

        $audit->handle('results.share_opened', metadata: ['campaign_id' => $campaign->id, 'share_id' => $share->id]);

        return self::private(Inertia::render('results/shared-report', [
            'company' => $company->tradeName,
            'expiresAt' => ResultsClock::moment($share->expires_at),
            'report' => $builder->build($campaign, $guide),
            'levels' => RiskLevels::catalog(),
        ])->toResponse($request));
    }

    private static function private(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
