<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Interventions\Actions\CancelAction;
use App\Modules\Interventions\Actions\RecordProgress;
use App\Modules\Interventions\Http\Requests\CancelActionRequest;
use App\Modules\Interventions\Http\Requests\ProgressRequest;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The control of the progress of the Programa (num. 8.4 d): steps are recorded and actions cancelled, never edited or
 * deleted. An action that is not of the Programa of the evaluation in the URL, or of this company, is not found.
 */
final class ProgressController extends Controller
{
    public function store(ProgressRequest $request, mixed $tenant, string $campaign, string $action, RecordProgress $record): RedirectResponse
    {
        $found = $this->find($campaign, $action);
        $record->handle($found->id, $request->status(), $request->string('recorded_on')->toString(), $request->note(), $request->user()->id ?? abort(403));
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

    private function find(string $campaign, string $action): ProgramAction
    {
        $program = Program::query()->where('campaign_id', $campaign)->firstOrFail();

        return ProgramAction::query()->where('program_id', $program->id)->findOrFail($action);
    }
}
