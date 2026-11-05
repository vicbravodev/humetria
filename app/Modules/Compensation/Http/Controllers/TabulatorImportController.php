<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ImportTabulator;
use App\Modules\Compensation\Http\Requests\SalaryImportRequest;
use App\Modules\Compensation\Support\TabulatorCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Loading the tabulator from a CSV: the file is read whole and written only when every row is valid. Never half a file.
 */
final class TabulatorImportController extends Controller
{
    public function template(): Response
    {
        Gate::authorize('view-compensation');

        return new Response(TabulatorCsv::template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-tabulador.csv"',
        ]);
    }

    // The $tenant parameter receives the company Tenancy resolved from the URL; it is only there to keep the route
    // parameters in order.
    public function store(SalaryImportRequest $request, mixed $tenant, ImportTabulator $import): RedirectResponse
    {
        $outcome = $import->handle($request->content());

        if ($outcome->imported()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => sprintf('Se importó el tabulador: %d %s.', $outcome->rows, $outcome->rows === 1 ? 'salario' : 'salarios')]);
        } else {
            Inertia::flash('salary_import', $outcome->summary());
        }

        return back();
    }
}
