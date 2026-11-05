<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Compensation\Models\ReportShare;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\ReportBuilder;
use App\Modules\Compensation\Support\ShareMoments;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The report of a scenario opened from a temporary link, with no account: the token is the credential. It shows the report
 * with the groups under the platform's minimum folded into «Otros», and no annex. A link unknown, expired, revoked, of a
 * blocked company or of a scenario that is gone opens the same page that names no company, as a 404. Each opening goes to
 * the audit trail. Never indexed, never cached.
 */
final class SharedReportController extends Controller
{
    public function show(Request $request, string $token, Companies $companies, ReportBuilder $builder, RecordAudit $audit): Response
    {
        // Read past the company scope: until the link is known, no company is.
        $share = ReportShare::query()->withoutGlobalScopes()->open()->where('token_hash', ReportShare::hashToken($token))->first();
        $company = $share === null ? null : $companies->enter($share->tenant_id);
        $scenario = $share === null || $company === null || $company->blocked ? null : Scenario::query()->find($share->scenario_id);

        if ($share === null || $company === null || $scenario === null) {
            return self::private(Inertia::render('compensation/shared-report', ['company' => null, 'expiresAt' => null, 'report' => null])->toResponse($request)->setStatusCode(404));
        }

        $audit->handle('compensation.share_opened', metadata: ['scenario_id' => $scenario->id, 'share_id' => $share->id]);

        return self::private(Inertia::render('compensation/shared-report', [
            'company' => $company->tradeName,
            'expiresAt' => ShareMoments::text($share->expires_at),
            'report' => [...$builder->build($scenario, shared: true), 'can_annex' => false, 'shares' => null],
        ])->toResponse($request));
    }

    private static function private(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
