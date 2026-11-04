<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\ScenarioComparison;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** «Comparar escenarios»: two to four proposals side by side, by concept. */
final class ScenarioComparisonController extends Controller
{
    /**
     * @param  mixed  $tenant  The company, already resolved by Tenancy from the URL; only routed through.
     */
    public function __invoke(Request $request, mixed $tenant, ScenarioComparison $comparison): Response
    {
        Gate::authorize('view-compensation');

        /** @var array{ids: list<string>} $data */
        $data = $request->validate(['ids' => ['required', 'array', 'min:2', 'max:4'], 'ids.*' => ['required', 'uuid', 'distinct']]);
        $scenarios = [];

        foreach ($data['ids'] as $id) {
            $scenarios[] = Scenario::query()->findOrFail($id);
        }

        return Inertia::render('compensation/scenario-compare', $comparison->handle($scenarios));
    }
}
