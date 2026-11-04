<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ArchiveScenario;
use App\Modules\Compensation\Actions\DuplicateScenario;
use App\Modules\Compensation\Actions\ListScenarios;
use App\Modules\Compensation\Actions\SaveScenario;
use App\Modules\Compensation\Http\Requests\ScenarioRequest;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\ScenarioEditor;
use App\Modules\Compensation\Support\ScenarioRows;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Escenarios»: the proposals to revise the contract. Each is costed on the spot from the roster, the tabulator and the
 * contract as they stand. A scenario is duplicated to try a variation and archived instead of deleted.
 */
final class ScenarioController extends Controller
{
    public function index(Request $request, ListScenarios $list, ScenarioRows $rows): Response
    {
        Gate::authorize('view-compensation');

        $archived = $request->string('status')->toString() === 'archived';

        return Inertia::render('compensation/scenarios', [
            'scenarios' => $rows->through($list->handle($archived)),
            'counts' => $list->counts(),
            'filters' => ['status' => $archived ? 'archived' : 'active'],
        ]);
    }

    public function create(ScenarioEditor $editor): Response
    {
        Gate::authorize('manage-compensation');

        return Inertia::render('compensation/scenario', $editor->props(null, $this->today()));
    }

    public function edit(mixed $tenant, string $scenario, ScenarioEditor $editor): Response
    {
        Gate::authorize('view-compensation');

        return Inertia::render('compensation/scenario', $editor->props(Scenario::query()->findOrFail($scenario), $this->today()));
    }

    // The $tenant parameters receive the company Tenancy resolved from the URL; they are only there to keep the route
    // parameters in order.
    public function store(ScenarioRequest $request, SaveScenario $save): RedirectResponse
    {
        $save->handle($request->toData());

        return $this->done('Escenario guardado.');
    }

    public function update(ScenarioRequest $request, mixed $tenant, string $scenario, SaveScenario $save): RedirectResponse
    {
        $save->handle($request->toData(Scenario::query()->findOrFail($scenario)->id));

        return $this->done('Escenario guardado.');
    }

    public function duplicate(mixed $tenant, string $scenario, DuplicateScenario $duplicate): RedirectResponse
    {
        Gate::authorize('manage-compensation');
        $copy = $duplicate->handle(Scenario::query()->findOrFail($scenario));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Copia creada.']);

        return to_route('tenant.compensation.scenarios.edit', [$tenant, $copy->id]);
    }

    public function archive(mixed $tenant, string $scenario, ArchiveScenario $archive): RedirectResponse
    {
        Gate::authorize('manage-compensation');
        $archive->handle(Scenario::query()->findOrFail($scenario));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Escenario archivado.']);

        return back();
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tenant.compensation.scenarios.index', request()->route('tenant'));
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
    }
}
