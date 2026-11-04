<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ListTabulator;
use App\Modules\Compensation\Actions\SaveGradeSalary;
use App\Modules\Compensation\Http\Requests\GradeSalaryRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Tabulador»: the daily salary of each category and level of the company, by vigencia. A category and level without a
 * salary is listed too, since it is what the tabulator still lacks.
 */
final class TabulatorController extends Controller
{
    public function index(Request $request, ListTabulator $list): Response
    {
        Gate::authorize('view-compensation');

        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();

        return Inertia::render('compensation/tabulator', [
            'grades' => $list->handle($search, $today),
            'filters' => ['q' => $search],
        ]);
    }

    // The $tenant parameter receives the company Tenancy resolved from the URL; it is only there to keep the route
    // parameters in order.
    public function store(GradeSalaryRequest $request, mixed $tenant, SaveGradeSalary $save): RedirectResponse
    {
        $save->handle($request->toData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Salario guardado.']);

        return back();
    }
}
