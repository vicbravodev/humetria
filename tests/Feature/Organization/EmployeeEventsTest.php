<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Actions\ReactivateEmployee;
use App\Modules\Organization\Actions\UpdateEmployee;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Event;

mutates(DeactivateEmployee::class, ReactivateEmployee::class, UpdateEmployee::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->warehouse = WorkCenter::factory()->for($this->tenant)->create();
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id] + ['name' => 'Producción']);
    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $area->id, 'email' => 'ana@acme.mx']);
    $this->owner = ownerOf($this->tenant);
    Event::fake([EmployeeDeactivated::class, EmployeeMoved::class]);
});

/** The employee form as it is, changing only what is given. */
function eventsEmployeeForm(Employee $employee, array $changes = []): array
{
    return [
        'name' => $employee->name,
        'email' => $employee->email,
        'mobile' => $employee->mobile,
        'gender' => $employee->gender->value,
        'work_center_id' => $employee->work_center_id,
        'area' => 'Producción',
        'job_title' => $employee->job_title,
        'employee_number' => $employee->employee_number,
        ...$changes,
    ];
}

it('announces that a person was deactivated', function (): void {
    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]))->assertRedirect();

    Event::assertDispatched(EmployeeDeactivated::class, fn (EmployeeDeactivated $event): bool => $event->tenantId === $this->tenant->id && $event->employeeId === $this->employee->id);
});

it('announces that a person moved to another work center', function (): void {
    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->tenant, $this->employee->id]), eventsEmployeeForm($this->employee, ['work_center_id' => $this->warehouse->id]))
        ->assertSessionHasNoErrors();

    Event::assertDispatchedTimes(EmployeeMoved::class, 1);
    Event::assertDispatched(EmployeeMoved::class, fn (EmployeeMoved $event): bool => $event->tenantId === $this->tenant->id
        && $event->employeeId === $this->employee->id
        && $event->fromWorkCenterId === $this->plant->id
        && $event->toWorkCenterId === $this->warehouse->id);
});

it('announces no move when only the area or other data change', function (): void {
    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->tenant, $this->employee->id]), eventsEmployeeForm($this->employee, ['area' => 'Ventas', 'name' => 'Ana María López']))
        ->assertSessionHasNoErrors();

    Event::assertNotDispatched(EmployeeMoved::class);
});
