<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Interventions\Actions\AddManualItem;
use App\Modules\Interventions\Actions\RecordFollowUp;
use App\Modules\Interventions\Actions\SaveAnalysis;
use App\Modules\Interventions\Http\Requests\AnalysisRequest;
use App\Modules\Interventions\Http\Requests\FollowUpRequest;
use App\Modules\Interventions\Http\Requests\ItemRequest;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The rows of a Programa: HR adds one from the catalog, writes its analysis and schedules or records its follow-up
 * evaluation. A row that is not of the Programa of the evaluation in the URL, or of this company, is not found.
 */
final class ItemController extends Controller
{
    public function store(ItemRequest $request, mixed $tenant, string $campaign, AddManualItem $add): RedirectResponse
    {
        $program = Program::query()->where('campaign_id', $campaign)->firstOrFail();
        $add->handle($program->id, $request->scope(), $request->string('scope_key')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fila agregada al Programa.']);

        return redirect()->route('tenant.campaigns.program', [$tenant, $campaign]);
    }

    public function analysis(AnalysisRequest $request, mixed $tenant, string $campaign, string $item, SaveAnalysis $save): RedirectResponse
    {
        $save->handle($this->find($campaign, $item)->id, $request->filled('analysis') ? $request->string('analysis')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Análisis guardado.']);

        return redirect()->route('tenant.campaigns.program', [$tenant, $campaign]);
    }

    public function followUp(FollowUpRequest $request, mixed $tenant, string $campaign, string $item, RecordFollowUp $record): RedirectResponse
    {
        $record->handle($this->find($campaign, $item)->id, $request->text('follow_up_due_on'), $request->text('follow_up_done_on'), $request->text('follow_up_result'), $request->text('follow_up_campaign_id'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluación posterior guardada.']);

        return redirect()->route('tenant.campaigns.program', [$tenant, $campaign]);
    }

    private function find(string $campaign, string $item): ProgramItem
    {
        $program = Program::query()->where('campaign_id', $campaign)->firstOrFail();

        return ProgramItem::query()->where('program_id', $program->id)->findOrFail($item);
    }
}
