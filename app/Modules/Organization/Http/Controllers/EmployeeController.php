<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Actions\CreateEmployee;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Actions\ListEmployees;
use App\Modules\Organization\Actions\ReactivateEmployee;
use App\Modules\Organization\Actions\UpdateEmployee;
use App\Modules\Organization\Data\EmployeeFilters;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Http\Requests\EmployeeRequest;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Empleados»: the company's people. Their names and contact data are for those who manage the organization only.
 */
final class EmployeeController extends Controller
{
    public function __construct(private readonly WorkCenters $workCenters) {}

    public function index(Request $request, ListEmployees $listEmployees): Response
    {
        Gate::authorize('manage-organization');

        $filters = $this->filters($request);
        $centers = $this->workCenters->all();
        $centerNames = array_column(array_map(fn (WorkCenterSummary $center): array => ['id' => $center->id, 'name' => $center->name], $centers), 'name', 'id');

        return Inertia::render('organization/employees', [
            'employees' => $listEmployees->handle($filters)->through(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'mobile' => $employee->mobile,
                'gender' => $employee->gender->value,
                'gender_label' => $employee->gender->label(),
                'work_center_id' => $employee->work_center_id,
                'work_center' => $centerNames[$employee->work_center_id] ?? '',
                'area' => $employee->area->name ?? '',
                'job_title' => $employee->job_title,
                'employee_number' => $employee->employee_number,
                'deactivated_on' => $employee->deactivated_at?->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY'),
            ]),
            'filters' => [
                'q' => $filters->search,
                'work_center' => $filters->workCenterId,
                'area' => $filters->areaId,
                'status' => $filters->active ? 'active' : 'inactive',
            ],
            'workCenters' => array_values(array_map(
                fn (WorkCenterSummary $center): array => ['value' => $center->id, 'label' => $center->name],
                array_filter($centers, fn (WorkCenterSummary $center): bool => ! $center->archived),
            )),
            'areas' => Area::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Area $area): array => ['value' => $area->id, 'label' => $area->name])->all(),
            'genders' => array_map(fn (Gender $gender): array => ['value' => $gender->value, 'label' => $gender->label()], Gender::cases()),
        ]);
    }

    public function store(EmployeeRequest $request, CreateEmployee $createEmployee): RedirectResponse
    {
        Gate::authorize('manage-organization');
        $createEmployee->handle($request->toData());

        return $this->done('Empleado dado de alta.');
    }

    public function update(EmployeeRequest $request, mixed $tenant, string $employee, UpdateEmployee $updateEmployee): RedirectResponse
    {
        Gate::authorize('manage-organization');
        $updateEmployee->handle($this->find($employee, active: true), $request->toData());

        return $this->done('Empleado actualizado.');
    }

    public function deactivate(mixed $tenant, string $employee, DeactivateEmployee $deactivateEmployee): RedirectResponse
    {
        Gate::authorize('manage-organization');
        $deactivateEmployee->handle($this->find($employee, active: true));

        return $this->done('Empleado dado de baja.');
    }

    public function reactivate(mixed $tenant, string $employee, ReactivateEmployee $reactivateEmployee): RedirectResponse
    {
        Gate::authorize('manage-organization');
        $reactivateEmployee->handle($this->find($employee, active: false));

        return $this->done('Empleado reactivado.');
    }

    // The $tenant parameters receive the company Tenancy resolved from the URL; they are only there to keep the route
    // parameters in order.

    private function filters(Request $request): EmployeeFilters
    {
        $uuid = fn (string $key): ?string => Str::isUuid($request->string($key)->toString()) ? $request->string($key)->toString() : null;

        return new EmployeeFilters(
            search: $request->string('q')->trim()->limit(100, '')->toString(),
            workCenterId: $uuid('work_center'),
            areaId: $uuid('area'),
            active: $request->string('status')->toString() !== 'inactive',
        );
    }

    /**
     * Another company's employee is not found: the same 404 as one that does not exist. One that is not in the
     * expected state is a 409: a deactivated employee is not edited.
     */
    private function find(string $id, bool $active): Employee
    {
        $employee = Employee::query()->findOrFail($id);

        abort_if(($employee->deactivated_at === null) !== $active, 409);

        return $employee;
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
