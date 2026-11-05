<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\CreateReportShare;
use App\Modules\Compensation\Actions\RevokeReportShare;
use App\Modules\Compensation\Http\Requests\ShareRequest;
use App\Modules\Compensation\Models\ReportShare;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\ShareMoments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Temporary links to the report of a scenario, opened and revoked by whoever manages compensation. A new link is flashed once, in the clear. */
final class ReportShareController extends Controller
{
    public function store(ShareRequest $request, mixed $tenant, string $scenario, CreateReportShare $create): RedirectResponse
    {
        $found = Scenario::query()->findOrFail($scenario);
        $user = $request->user();
        abort_if($user === null, 403);

        [$share, $token] = $create->handle($found, $request->integer('days'), $user->id);
        Inertia::flash('reportLink', ['url' => route('compensation.report.shared', $token), 'expiresAt' => ShareMoments::text($share->expires_at)]);

        return back();
    }

    public function destroy(mixed $tenant, string $scenario, string $share, RevokeReportShare $revoke): RedirectResponse
    {
        Gate::authorize('manage-compensation');
        $found = ReportShare::query()->where('scenario_id', Scenario::query()->findOrFail($scenario)->id)->findOrFail($share);

        $revoke->handle($found);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace revocado.']);

        return back();
    }
}
