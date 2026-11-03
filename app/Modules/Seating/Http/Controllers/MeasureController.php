<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\CancelMeasure;
use App\Modules\Seating\Actions\ProposeMeasure;
use App\Modules\Seating\Actions\SaveMeasure;
use App\Modules\Seating\Http\Requests\MeasureRequest;
use App\Modules\Seating\Http\Requests\ProposeMeasureRequest;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Support\AssessmentAccess;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\SeatingFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The measures of the action plan. The Comisión of the center proposes them from an assessment; RH registers them, follows
 * them up and cancels them. Nothing is deleted (criterio C-17); the evidence leaves only through an authorized route.
 */
final class MeasureController extends Controller
{
    public function propose(ProposeMeasureRequest $request, mixed $tenant, string $assessment, CenterAccess $access, AssessmentAccess $assessors, ProposeMeasure $propose): RedirectResponse
    {
        $found = RiskAssessment::query()->find($assessment);
        abort_if($found === null, 404);
        $access->find($request, $found->work_center_id);
        abort_unless($assessors->canAssess($access->userId($request), $found->work_center_id), 403);

        $propose->handle($found, $request->kind(), $request->note(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medida propuesta. RH la dará seguimiento en el plan de acción.']);

        return redirect()->route('tenant.seating.assessments.show', [$tenant, $found->id]);
    }

    public function store(MeasureRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, SaveMeasure $save): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $save->handle($request->toData(null, $center->id), $request->evidenceFile(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medida registrada.']);

        return redirect()->route('tenant.seating.plan', [$tenant, $center->id]);
    }

    public function update(MeasureRequest $request, mixed $tenant, string $measure, CenterAccess $access, SaveMeasure $save): RedirectResponse
    {
        $found = $this->measure($request, $measure, $access);
        $save->handle($request->toData($found->id, $found->work_center_id), $request->evidenceFile(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medida actualizada.']);

        return redirect()->route('tenant.seating.plan', [$tenant, $found->work_center_id]);
    }

    public function cancel(Request $request, mixed $tenant, string $measure, CenterAccess $access, CancelMeasure $cancel): RedirectResponse
    {
        Gate::authorize('manage-seating');
        $found = $this->measure($request, $measure, $access);
        $cancel->handle($found);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medida cancelada. Queda en el plan como evidencia.']);

        return redirect()->route('tenant.seating.plan', [$tenant, $found->work_center_id]);
    }

    public function evidence(Request $request, mixed $tenant, string $measure, CenterAccess $access, SeatingFiles $files): StreamedResponse
    {
        abort_unless(Gate::allows('manage-seating') || Gate::allows('assess-seating'), 403);
        $found = $this->measure($request, $measure, $access);
        abort_if($found->evidence_path === null, 404);

        $response = $files->stream($found->evidence_path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function measure(Request $request, string $id, CenterAccess $access): PreventiveMeasure
    {
        $found = PreventiveMeasure::query()->find($id);
        abort_if($found === null, 404);
        $access->find($request, $found->work_center_id);

        return $found;
    }
}
