<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\CreateEmployee;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Actions\ImportEmployees;
use App\Modules\Organization\Actions\ReactivateEmployee;
use App\Modules\Organization\Actions\UpdateEmployee;
use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;

mutates(CreateEmployee::class, ImportEmployees::class, DeactivateEmployee::class, ReactivateEmployee::class, UpdateEmployee::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->warehouse = WorkCenter::factory()->for($this->tenant)->create();
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id] + ['name' => 'Producción']);
    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $area->id, 'email' => 'ana@acme.mx']);
    $this->owner = ownerOf($this->tenant);
    Event::fake([EmployeeCreated::class, EmployeeDeactivated::class, EmployeeReactivated::class, EmployeeMoved::class]);
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

it('announces that a person was deactivated, and reactivated', function (): void {
    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]))->assertRedirect();

    Event::assertDispatched(EmployeeDeactivated::class, fn (EmployeeDeactivated $event): bool => $event->tenantId === $this->tenant->id && $event->employeeId === $this->employee->id);
    Event::assertNotDispatched(EmployeeReactivated::class);

    $this->actingAs($this->owner)->post(route('tenant.employees.reactivate', [$this->tenant, $this->employee->id]))->assertRedirect();

    Event::assertDispatchedTimes(EmployeeReactivated::class, 1);
    Event::assertDispatched(EmployeeReactivated::class, fn (EmployeeReactivated $event): bool => $event->tenantId === $this->tenant->id
        && $event->employeeId === $this->employee->id
        && $event->workCenterId === $this->plant->id);
    expect(new EmployeeReactivated('t', 'e', 'w'))->toBeInstanceOf(ShouldDispatchAfterCommit::class);
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

it('announces a person created by hand, once and after commit', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), eventsEmployeeForm($this->employee, ['email' => 'nueva@acme.mx', 'employee_number' => 'N-77', 'name' => 'Nueva Persona']))
        ->assertSessionHasNoErrors();

    workIn($this->tenant);
    $created = Employee::query()->where('employee_number', 'N-77')->sole();

    Event::assertDispatchedTimes(EmployeeCreated::class, 1);
    Event::assertDispatched(EmployeeCreated::class, fn (EmployeeCreated $event): bool => $event->tenantId === $this->tenant->id
        && $event->employeeId === $created->id
        && $event->workCenterId === $this->plant->id);
    expect(new EmployeeCreated('t', 'e', 'w'))->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

it('announces each person an import creates, and not those it updates', function (): void {
    $file = UploadedFile::fake()->createWithContent('empleados.csv', implode("\n", [
        'nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado',
        "{$this->employee->name},ana@acme.mx,,Mujer,{$this->plant->name},Producción,Operadora,",
        "Beto Díaz,beto@acme.mx,,Hombre,{$this->plant->name},Producción,Almacenista,B-1",
        "Carla Ruiz,,5587654321,Otro,{$this->warehouse->name},Ventas,Vendedora,C-3",
    ])."\n");

    $this->actingAs($this->owner)->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => $file])->assertSessionHasNoErrors();

    Event::assertDispatchedTimes(EmployeeCreated::class, 2);
    Event::assertDispatched(EmployeeCreated::class, fn (EmployeeCreated $event): bool => $event->workCenterId === $this->warehouse->id);
    Event::assertDispatched(EmployeeCreated::class, fn (EmployeeCreated $event): bool => $event->workCenterId === $this->plant->id);
});
