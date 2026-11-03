<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\SaveAssessmentDraft;
use App\Modules\Seating\Actions\SignAssessment;
use App\Modules\Seating\Actions\StartAssessment;
use App\Modules\Seating\Http\Requests\StartAssessmentRequest;
use App\Modules\Seating\Http\Requests\UpdateAssessmentRequest;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Support\AssessmentAccess;
use App\Modules\Seating\Support\AssessmentPages;
use App\Modules\Seating\Support\CenterAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The individual assessments of a work center. They hold identified health data, so the viewer gets a 403; the Comisión
 * reads only its centers (a 404 for the others) and only a member assigned to the center assesses and signs.
 */
final class AssessmentController extends Controller
{
    public function index(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, AssessmentPages $pages): Response
    {
        Gate::authorize('assess-seating');
        $center = $access->find($request, $workCenter);

        return Inertia::render('seating/assessments', $pages->index($center, $access->userId($request), $request->string('q')->trim()->limit(100, '')->toString()));
    }

    public function store(StartAssessmentRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, AssessmentAccess $assessors, StartAssessment $start): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $userId = $access->userId($request);
        abort_unless($assessors->canAssess($userId, $center->id), 403);

        $draft = $start->handle($center->id, $request->string('employee_id')->toString(), $userId);

        return redirect()->route('tenant.seating.assessments.show', [$tenant, $draft->id]);
    }

    public function show(Request $request, mixed $tenant, string $assessment, CenterAccess $access, AssessmentPages $pages): Response
    {
        Gate::authorize('assess-seating');
        $found = $this->assessment($request, $assessment, $access);

        return Inertia::render('seating/assessment', $pages->show($found, $access->userId($request)));
    }

    public function update(UpdateAssessmentRequest $request, mixed $tenant, string $assessment, CenterAccess $access, AssessmentAccess $assessors, SaveAssessmentDraft $save): RedirectResponse
    {
        $found = $this->assessment($request, $assessment, $access);
        abort_unless($assessors->canAssess($access->userId($request), $found->work_center_id), 403);
        $save->handle($found, $request->scores(), $request->details());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador guardado.']);

        return redirect()->route('tenant.seating.assessments.show', [$tenant, $found->id]);
    }

    public function sign(Request $request, mixed $tenant, string $assessment, CenterAccess $access, SignAssessment $sign): RedirectResponse
    {
        Gate::authorize('assess-seating');
        $found = $this->assessment($request, $assessment, $access);
        $sign->handle($found, $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluación firmada.']);

        return redirect()->route('tenant.seating.assessments.show', [$tenant, $found->id]);
    }

    private function assessment(Request $request, string $id, CenterAccess $access): RiskAssessment
    {
        $found = RiskAssessment::query()->find($id);
        abort_if($found === null, 404);
        $access->find($request, $found->work_center_id);

        return $found;
    }
}
