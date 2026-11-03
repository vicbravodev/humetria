<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\RecordReferral;
use App\Modules\Seating\Http\Requests\ReferralRequest;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Support\AssessmentAccess;
use App\Modules\Seating\Support\CenterAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Records a referral to medical attention for the person of an assessment: RH, or a member of the center's Comisión. The
 * viewer never reaches it; the Comisión of another center does not find the assessment.
 */
final class ReferralController extends Controller
{
    public function store(ReferralRequest $request, mixed $tenant, string $assessment, CenterAccess $access, AssessmentAccess $assessors, RecordReferral $record): RedirectResponse
    {
        $found = RiskAssessment::query()->find($assessment);
        abort_if($found === null, 404);
        $access->find($request, $found->work_center_id);
        $userId = $access->userId($request);
        abort_unless(Gate::allows('manage-seating') || $assessors->canAssess($userId, $found->work_center_id), 403);

        $record->handle($found, $request->boolean('referred'), $request->string('referred_on')->toString(), $request->note(), $userId);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Canalización registrada.']);

        return redirect()->route('tenant.seating.assessments.show', [$tenant, $found->id]);
    }
}
