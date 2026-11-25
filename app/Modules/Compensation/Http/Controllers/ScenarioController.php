<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ArchiveScenario;
use App\Modules\Compensation\Actions\DuplicateScenario;
use App\Modules\Compensation\Actions\ListScenarios;
use App\Modules\Compensation\Actions\SaveScenario;
use App\Modules\Compensation\Http\Requests\ScenarioRequest;
use App\Modules\Compensation\Http\ScenarioRoute;
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
 * contract as they stand, or, in «Cotizador rápido», from an average payroll typed in it. A scenario is duplicated to try a
 * variation and archived instead of deleted. The same pages serve both kinds: the route says which one it is.
 */
final class ScenarioController extends Controller
{
    public function index(Request $request, ListScenarios $list, ScenarioRows $rows): Response
    {
        Gate::authorize('view-compensation');

        $archived = $request->string('status')->toString() === 'archived';

        $source = ScenarioRoute::source($request);

        return Inertia::render('compensation/scenarios', [
            'source' => $source->value,
            'scenarios' => $rows->through($list->handle($archived, $source)),
            'counts' => $list->counts($source),
            'filters' => ['status' => $archived ? 'archived' : 'active'],
        ]);
    }

    public function create(Request $request, ScenarioEditor $editor): Response
    {
        Gate::authorize('manage-compensation');

        $prefill = [];

        // A link may start the scenario somewhere (the point in between of a comparison); the editor checks each value.
        foreach (['name', 'raise_unit', 'unionized_raise', 'trusted_raise', 'retroactive_months'] as $key) {
            $prefill[$key] = $request->query($key);
        }

        return Inertia::render('compensation/scenario', $editor->props(null, $this->today(), ScenarioRoute::source($request), $prefill));
    }

    public function edit(Request $request, mixed $tenant, string $scenario, ScenarioEditor $editor): Response|RedirectResponse
    {
        Gate::authorize('view-compensation');

        $found = Scenario::query()->findOrFail($scenario);

        // A scenario is edited where its kind lives: a link to the other pages follows it there.
        if ($found->source !== ScenarioRoute::source($request)) {
            return to_route(ScenarioRoute::name($found->source, 'edit'), [$tenant, $found->id]);
        }

        return Inertia::render('compensation/scenario', $editor->props($found, $this->today(), $found->source));
    }

    // The $tenant parameters receive the company Tenancy resolved from the URL; they are only there to keep the route
    // parameters in order.
    public function store(ScenarioRequest $request, SaveScenario $save): RedirectResponse
    {
        return $this->done($save->handle($request->toData()));
    }

    public function update(ScenarioRequest $request, mixed $tenant, string $scenario, SaveScenario $save): RedirectResponse
    {
        $found = Scenario::query()->findOrFail($scenario);

        // The form of one kind never rewrites a scenario of the other.
        abort_unless($found->source === $request->source(), 404);

        return $this->done($save->handle($request->toData($found->id)));
    }

    public function duplicate(mixed $tenant, string $scenario, DuplicateScenario $duplicate): RedirectResponse
    {
        Gate::authorize('manage-compensation');
        $copy = $duplicate->handle(Scenario::query()->findOrFail($scenario));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Copia creada.']);

        return to_route(ScenarioRoute::name($copy->source, 'edit'), [$tenant, $copy->id]);
    }

    public function archive(mixed $tenant, string $scenario, ArchiveScenario $archive): RedirectResponse
    {
        Gate::authorize('manage-compensation');
        $archive->handle(Scenario::query()->findOrFail($scenario));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Escenario archivado.']);

        return back();
    }

    /** «Guardar y ver reporte» (board V4-Comp-Escenario): a saved scenario opens its report. */
    private function done(Scenario $saved): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Escenario guardado.']);

        return to_route(ScenarioRoute::name($saved->source, 'report'), [request()->route('tenant'), $saved->id]);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
    }
}
