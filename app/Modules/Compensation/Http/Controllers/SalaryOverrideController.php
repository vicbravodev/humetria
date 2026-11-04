<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ListSalaryOverrides;
use App\Modules\Compensation\Actions\SaveSalaryOverride;
use App\Modules\Compensation\Http\Requests\OverrideRequest;
use App\Modules\Compensation\Support\CsvFile;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Sueldos individuales»: the few people who do not earn what their category and level say. Each is told apart by the
 * company's employee number, and what they earned before is kept.
 */
final class SalaryOverrideController extends Controller
{
    public function index(Request $request, ListSalaryOverrides $list): Response
    {
        Gate::authorize('view-compensation');

        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();

        return Inertia::render('compensation/overrides', [
            'people' => $list->handle($search, $today),
            'filters' => ['q' => $search],
            'max_rows' => CsvFile::MAX_ROWS,
        ]);
    }

    // The $tenant parameter receives the company Tenancy resolved from the URL; it is only there to keep the route
    // parameters in order.
    public function store(OverrideRequest $request, mixed $tenant, SaveSalaryOverride $save): RedirectResponse
    {
        $save->handle($request->toData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sueldo individual guardado.']);

        return back();
    }
}
