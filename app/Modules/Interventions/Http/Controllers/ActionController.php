<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Interventions\Actions\SaveAction;
use App\Modules\Interventions\Http\Requests\ActionRequest;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The actions of a Programa: owner and RH add and edit them. A row or an action that is not of the Programa of the
 * evaluation in the URL, or of this company, is not found.
 */
final class ActionController extends Controller
{
    public function store(ActionRequest $request, mixed $tenant, string $campaign, string $item, SaveAction $save): RedirectResponse
    {
        $program = Program::query()->where('campaign_id', $campaign)->firstOrFail();
        $row = ProgramItem::query()->where('program_id', $program->id)->findOrFail($item);
        $save->handle($request->toData(null, $row->id), $request->user()->id ?? abort(403));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acción registrada.']);

        return redirect()->route('tenant.campaigns.program', [$tenant, $campaign]);
    }

    public function update(ActionRequest $request, mixed $tenant, string $campaign, string $action, SaveAction $save): RedirectResponse
    {
        $program = Program::query()->where('campaign_id', $campaign)->firstOrFail();
        $found = ProgramAction::query()->where('program_id', $program->id)->findOrFail($action);
        $save->handle($request->toData($found->id, null), $request->user()->id ?? abort(403));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acción actualizada.']);

        return redirect()->route('tenant.campaigns.program', [$tenant, $campaign]);
    }
}
