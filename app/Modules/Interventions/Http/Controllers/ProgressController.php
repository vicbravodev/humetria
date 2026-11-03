<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Interventions\Actions\CancelAction;
use App\Modules\Interventions\Actions\RecordProgress;
use App\Modules\Interventions\Http\Requests\CancelActionRequest;
use App\Modules\Interventions\Http\Requests\ProgressRequest;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Interventions\Support\InterventionFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The control of the progress of the Programa (num. 8.4 d): steps are recorded and actions cancelled, never edited or
 * deleted. An action that is not of the Programa of the evaluation in the URL, or of this company, is not found.
 */
final class ProgressController extends Controller
{
    public function store(ProgressRequest $request, mixed $tenant, string $campaign, string $action, RecordProgress $record): RedirectResponse
    {
        $found = $this->find($campaign, $action);
        $record->handle($found->id, $request->status(), $request->string('recorded_on')->toString(), $request->note(), $request->user()->id ?? abort(403), $request->evidenceFile());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Avance registrado.']);

        return redirect()->route('tenant.campaigns.program', [$tenant, $campaign]);
    }

    public function cancel(CancelActionRequest $request, mixed $tenant, string $campaign, string $action, CancelAction $cancel): RedirectResponse
    {
        $found = $this->find($campaign, $action);
        $cancel->handle($found->id, $request->string('reason')->toString(), $request->user()->id ?? abort(403));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acción cancelada. Queda en el Programa como evidencia.']);

        return redirect()->route('tenant.campaigns.program', [$tenant, $campaign]);
    }

    /** The file of evidence of a step, for those who write the Programa: a list of attendance carries names. */
    public function evidence(Request $request, mixed $tenant, string $campaign, string $action, string $entry, InterventionFiles $files, RecordAudit $audit): StreamedResponse
    {
        Gate::authorize('manage-interventions');
        $found = $this->find($campaign, $action);
        $step = ProgressEntry::query()->where('action_id', $found->id)->findOrFail($entry);
        abort_if($step->evidence_path === null, 404);

        $audit->handle('interventions.evidence_viewed', metadata: ['action_id' => $found->id, 'program_id' => $found->program_id, 'entry_id' => $step->id]);
        $response = $files->stream($step->evidence_path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function find(string $campaign, string $action): ProgramAction
    {
        $program = Program::query()->where('campaign_id', $campaign)->firstOrFail();

        return ProgramAction::query()->where('program_id', $program->id)->findOrFail($action);
    }
}
