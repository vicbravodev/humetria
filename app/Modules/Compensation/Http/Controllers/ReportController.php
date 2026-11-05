<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ListReportShares;
use App\Modules\Compensation\Actions\OpenReport;
use App\Modules\Compensation\Models\Scenario;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The report of a scenario: what it costs in the first year and over the years, with its assumptions. */
final class ReportController extends Controller
{
    public function show(mixed $tenant, string $scenario, OpenReport $open, ListReportShares $shares): Response
    {
        Gate::authorize('view-compensation');

        $found = Scenario::query()->findOrFail($scenario);
        $manages = Gate::allows('manage-compensation');

        return Inertia::render('compensation/report', [
            ...$open->handle($found),
            'can_annex' => $manages,
            'shares' => $manages ? $shares->handle($found->id) : null,
        ]);
    }
}
