<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ListReportShares;
use App\Modules\Compensation\Actions\OpenReport;
use App\Modules\Compensation\Enums\ScenarioSource;
use App\Modules\Compensation\Http\ScenarioRoute;
use App\Modules\Compensation\Models\Scenario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The report of a scenario: what it costs in the first year and over the years, with its assumptions. */
final class ReportController extends Controller
{
    public function show(Request $request, mixed $tenant, string $scenario, OpenReport $open, ListReportShares $shares): Response|RedirectResponse
    {
        Gate::authorize('view-compensation');

        $found = Scenario::query()->findOrFail($scenario);

        // The report of a scenario is at the pages of its kind: a link to the other ones follows it there.
        if ($found->source !== ScenarioRoute::source($request)) {
            return to_route(ScenarioRoute::name($found->source, 'report'), [$tenant, $found->id]);
        }

        $manages = Gate::allows('manage-compensation');

        return Inertia::render('compensation/report', [
            ...$open->handle($found),
            'can_manage' => $manages,
            // The annex lists people: an average payroll has none.
            'can_annex' => $manages && $found->source === ScenarioSource::Roster,
            'shares' => $manages ? $shares->handle($found->id) : null,
        ]);
    }
}
