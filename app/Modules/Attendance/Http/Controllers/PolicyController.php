<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\SavePolicy;
use App\Modules\Attendance\Http\Requests\PolicyRequest;
use App\Modules\Attendance\Support\CompanyPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The company's policy of the registro (criterios 4.1 to 4.4 and 7.1): owner, hr and viewer read it, owner and hr change it.
 * While none is saved the page shows the defaults it starts with.
 */
final readonly class PolicyController
{
    public function show(): Response
    {
        Gate::authorize('view-attendance');
        $policy = CompanyPolicy::current();

        return Inertia::render('attendance/policy', [
            'policy' => [
                'outOfZone' => $policy->out_of_zone->value,
                'maxAccuracyM' => $policy->max_accuracy_m,
                'requireQrOnsite' => $policy->require_qr_onsite,
                'trustedMayUseBrowser' => $policy->trusted_may_use_browser,
                'retentionMonths' => $policy->retention_months,
            ],
            'saved' => $policy->exists,
            'canManage' => Gate::allows('manage-attendance'),
        ]);
    }

    public function update(PolicyRequest $request, SavePolicy $save): RedirectResponse
    {
        $save->handle($request->outOfZone(), $request->maxAccuracy(), $request->requireQrOnsite(), $request->trustedMayUseBrowser(), $request->retentionMonths());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Política guardada. Aplica desde la siguiente marca.']);

        return back();
    }
}
