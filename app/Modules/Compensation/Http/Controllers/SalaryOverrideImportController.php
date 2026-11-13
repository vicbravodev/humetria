<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ImportSalaryOverrides;
use App\Modules\Compensation\Http\Requests\SalaryImportRequest;
use App\Modules\Compensation\Support\OverridesCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Loading the individual salaries from a CSV: the file is read whole and written only when every row is valid. Never half
 * a file.
 */
final class SalaryOverrideImportController extends Controller
{
    public function template(): Response
    {
        Gate::authorize('view-compensation');

        return new Response(OverridesCsv::template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-sueldos-individuales.csv"',
        ]);
    }

    // The $tenant parameter receives the company Tenancy resolved from the URL; it is only there to keep the route
    // parameters in order.
    public function store(SalaryImportRequest $request, mixed $tenant, ImportSalaryOverrides $import): RedirectResponse
    {
        $outcome = $import->handle($request->content());

        if ($outcome->imported()) {
            $note = $outcome->lowSalaryNote();
            $message = $outcome->rows === 1 ? 'Se importó 1 sueldo individual.' : sprintf('Se importaron %d sueldos individuales.', $outcome->rows);
            Inertia::flash('toast', ['type' => $note === null ? 'success' : 'warning', 'message' => $note === null ? $message : "{$message} {$note}"]);
        } else {
            Inertia::flash('salary_import', $outcome->summary());
        }

        return back();
    }
}
