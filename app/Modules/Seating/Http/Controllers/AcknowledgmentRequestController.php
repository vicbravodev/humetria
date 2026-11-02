<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\RequestAcknowledgment;
use App\Modules\Seating\Actions\RequestAcknowledgmentsForCenter;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Support\CenterAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * RH asks again for the confirmation of an assessment, of one person or of everyone in a center who has not confirmed.
 */
final class AcknowledgmentRequestController extends Controller
{
    public function resend(Request $request, mixed $tenant, string $assessment, CenterAccess $access, RequestAcknowledgment $requests): RedirectResponse
    {
        Gate::authorize('manage-seating');
        $found = RiskAssessment::query()->where('status', AssessmentStatus::Signed)->find($assessment);
        abort_if($found === null, 404);
        $access->find($request, $found->work_center_id);

        $requests->handle($found);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le enviamos otra vez el enlace.']);

        return redirect()->route('tenant.seating.assessments.show', [$tenant, $found->id]);
    }

    public function batch(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, RequestAcknowledgmentsForCenter $requests): RedirectResponse
    {
        Gate::authorize('manage-seating');
        $center = $access->find($request, $workCenter);

        $result = $requests->handle($center->id);
        $message = "Enviamos {$result['queued']} correos.".($result['withoutEmail'] > 0 ? " {$result['withoutEmail']} personas no tienen correo: ábreles su página desde su evaluación." : '');
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('tenant.seating.assessments.index', [$tenant, $center->id]);
    }
}
