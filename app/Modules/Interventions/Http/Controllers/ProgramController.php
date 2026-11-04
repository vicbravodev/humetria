<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Actions\SaveProgramResponsible;
use App\Modules\Interventions\Http\Requests\ResponsibleRequest;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Support\ProgramPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Programa de intervención of a closed evaluation (num. 8.3 and 8.4). Owner and RH write it; the viewer reads it. An
 * evaluation of another company, one that still runs and one of only the Guía I are not found.
 */
final class ProgramController extends Controller
{
    public function __construct(private readonly CampaignRecords $campaigns) {}

    public function show(Request $request, mixed $tenant, string $campaign, ProgramPages $pages, RecordAudit $audit): Response
    {
        Gate::authorize('view-interventions');
        $found = $this->find($campaign);
        $program = Program::query()->where('campaign_id', $found->id)->first();
        $audit->handle('interventions.program_viewed', metadata: ['campaign_id' => $found->id]);

        $item = $request->query('item');
        $status = $request->query('status');

        return Inertia::render('interventions/show', $pages->show($found, $program, $request->user()?->can('manage-interventions') ?? false, is_string($item) ? $item : null, is_string($status) ? $status : null));
    }

    public function store(Request $request, mixed $tenant, string $campaign, CreateProgram $create): RedirectResponse
    {
        Gate::authorize('manage-interventions');
        $found = $this->find($campaign);
        $program = $create->handle($found->id, $request->user()?->id);
        Inertia::flash('toast', $program === null
            ? ['type' => 'info', 'message' => 'Ningún cuestionario quedó en riesgo medio o más: la norma no pide un Programa.']
            : ['type' => 'success', 'message' => 'Programa creado. Completa las acciones de cada fila.']);

        return redirect()->route('tenant.campaigns.program', [$tenant, $found->id]);
    }

    public function responsible(ResponsibleRequest $request, mixed $tenant, string $campaign, SaveProgramResponsible $save): RedirectResponse
    {
        $program = Program::query()->where('campaign_id', $campaign)->firstOrFail();
        $save->handle($program->id, $request->userId(), $request->name());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Responsable guardado.']);

        return redirect()->route('tenant.campaigns.program', [$tenant, $campaign]);
    }

    /** Another company's evaluation is not found, the same 404 as one that does not exist. */
    private function find(string $id): CampaignRecord
    {
        return $this->campaigns->find($id) ?? abort(404);
    }
}
