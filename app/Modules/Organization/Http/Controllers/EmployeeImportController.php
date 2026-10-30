<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Actions\ImportEmployees;
use App\Modules\Organization\Actions\PlanEmployeeImport;
use App\Modules\Organization\Http\Requests\ImportEmployeesRequest;
use App\Modules\Organization\Support\EmployeeCsv;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loading employees from a CSV: the file is checked whole first (a preview of what it would add and update, or the
 * problems of each row), and written only when it is valid and the person confirms. Never half a file.
 */
final class EmployeeImportController extends Controller
{
    public function __construct(private readonly WorkCenters $workCenters) {}

    public function create(): Response
    {
        Gate::authorize('manage-organization');

        return Inertia::render('organization/employee-import', [
            'maxRows' => EmployeeCsv::MAX_ROWS,
            'hasWorkCenters' => $this->activeCenters() !== [],
        ]);
    }

    public function template(): HttpResponse
    {
        Gate::authorize('manage-organization');

        return new HttpResponse(EmployeeCsv::template($this->activeCenters()[0]->name ?? 'Matriz'), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-empleados.csv"',
        ]);
    }

    /**
     * @param  mixed  $tenant  The company, already resolved by Tenancy from the URL; only routed through.
     */
    public function store(ImportEmployeesRequest $request, mixed $tenant, PlanEmployeeImport $planImport, ImportEmployees $importEmployees): RedirectResponse
    {
        $read = EmployeeCsv::read($request->content());

        if ($read['error'] !== null) {
            throw ValidationException::withMessages(['file' => $read['error']]);
        }

        $plan = $planImport->handle($read['rows']);

        if (! $plan->isValid() || ! $request->confirmed()) {
            Inertia::flash('import', $plan->summary());

            return back();
        }

        $counts = $importEmployees->handle($plan);
        Inertia::flash('toast', ['type' => 'success', 'message' => self::done($counts['created'], $counts['updated'])]);

        return redirect()->route('tenant.employees.index', ['tenant' => $request->route('tenant')]);
    }

    /** "Se importaron 3 empleados: 1 nuevo y 2 actualizados." */
    private static function done(int $created, int $updated): string
    {
        $total = $created + $updated;

        return sprintf(
            'Se importaron %d %s: %d %s y %d %s.',
            $total, $total === 1 ? 'empleado' : 'empleados',
            $created, $created === 1 ? 'nuevo' : 'nuevos',
            $updated, $updated === 1 ? 'actualizado' : 'actualizados',
        );
    }

    /**
     * @return list<WorkCenterSummary>
     */
    private function activeCenters(): array
    {
        return array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived));
    }
}
