<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Actions\CreateEmployee;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Actions\ReactivateEmployee;
use App\Modules\Organization\Actions\UpdateEmployee;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Http\Requests\EmployeeRequest;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(CreateEmployee::class, UpdateEmployee::class, DeactivateEmployee::class, ReactivateEmployee::class, EmployeeRequest::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
});

function employeeForm(WorkCenter $workCenter, array $overrides = []): array
{
    return [
        'name' => 'Ana López',
        'email' => 'Ana@Acme.MX',
        'mobile' => '(55) 1234-5678',
        'gender' => 'female',
        'work_center_id' => $workCenter->id,
        'area' => 'Producción',
        'job_title' => 'Operadora',
        'employee_number' => 'A-001',
        ...$overrides,
    ];
}

/** Every employee in the table, whatever its company. */
function storedEmployee(): Employee
{
    return Employee::query()->withoutGlobalScopes()->sole();
}

it('adds an employee, creating the area the first time it is named', function (): void {
    Log::spy();

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), employeeForm($this->plant))
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empleado dado de alta.']]);

    $employee = storedEmployee();
    $area = Area::query()->withoutGlobalScopes()->sole();

    expect($employee->only(['tenant_id', 'work_center_id', 'area_id', 'name', 'email', 'mobile', 'job_title', 'employee_number']))->toBe([
        'tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $area->id, 'name' => 'Ana López',
        'email' => 'ana@acme.mx', 'mobile' => '5512345678', 'job_title' => 'Operadora', 'employee_number' => 'A-001',
    ])->and($employee->gender)->toBe(Gender::Female)
        ->and($area->only(['tenant_id', 'name']))->toBe(['tenant_id' => $this->tenant->id, 'name' => 'Producción'])
        ->and(AuditEntry::query()->count())->toBe(0);

    Log::shouldHaveReceived('info', ['area.created', ['area_id' => $area->id]])->once();
    Log::shouldHaveReceived('info', ['employee.created', ['employee_id' => $employee->id]])->once();
});

it('reuses an area the company has, whatever the case', function (): void {
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    Area::factory()->create(['name' => 'Producción']);

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), employeeForm($this->plant, ['area' => ' PRODUCCIÓN ']))
        ->assertSessionHasNoErrors();

    expect(storedEmployee()->area_id)->toBe($area->id)
        ->and(Area::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('takes a mobile without an email, or an email without a mobile', function (array $contact): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), employeeForm($this->plant, $contact))
        ->assertSessionHasNoErrors();

    expect(storedEmployee()->only(['email', 'mobile']))->toBe([
        'email' => $contact['email'] === '' ? null : 'ana@acme.mx',
        'mobile' => $contact['mobile'] === '' ? null : '5512345678',
    ]);
})->with([
    'mobile only' => [['email' => '', 'mobile' => '+52 55 1234 5678']],
    'email only' => [['email' => 'ana@acme.mx', 'mobile' => '']],
]);

it('validates the employee', function (array $overrides, string $field, string $message): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), employeeForm($this->plant, $overrides))
        ->assertSessionHasErrors([$field => $message]);

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([
    'no contact' => [['email' => '', 'mobile' => ''], 'email', 'Escribe un correo o un celular.'],
    'short mobile' => [['mobile' => '55 1234'], 'mobile', 'Escribe un celular de 10 dígitos, por ejemplo 55 1234 5678.'],
    'bad email' => [['email' => 'ana'], 'email', 'El campo correo electrónico debe ser un correo electrónico válido.'],
    'gender' => [['gender' => 'x'], 'gender', 'Elige una opción de la lista.'],
    'no name' => [['name' => ''], 'name', 'El campo nombre es obligatorio.'],
    'no area' => [['area' => ''], 'area', 'El campo área es obligatorio.'],
    'no job title' => [['job_title' => ''], 'job_title', 'El campo puesto es obligatorio.'],
    'unknown center' => [['work_center_id' => '0198c3a2-0000-4000-8000-000000000000'], 'work_center_id', 'Elige un centro de trabajo de la lista.'],
    'not a uuid' => [['work_center_id' => 'x'], 'work_center_id', 'Elige un centro de trabajo de la lista.'],
]);

it('rejects a work center archived or of another company', function (bool $archived): void {
    $center = $archived ? WorkCenter::factory()->for($this->tenant)->archived()->create() : WorkCenter::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), employeeForm($this->plant, ['work_center_id' => $center->id]))
        ->assertSessionHasErrors(['work_center_id' => 'Elige un centro de trabajo de la lista.']);
})->with(['archived' => true, 'other company' => false]);

it('rejects an email or number another employee of the company has', function (string $field, string $message): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'email' => 'ana@acme.mx', 'employee_number' => 'a-001']);

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), employeeForm($this->plant, $field === 'email' ? ['employee_number' => ''] : ['email' => 'otra@acme.mx']))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'email' => ['email', 'Ya hay un empleado con este correo.'],
    'number' => ['employee_number', 'Ya hay un empleado con este número.'],
]);

it('corrects an employee, keeping their own email and number', function (): void {
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'email' => 'ana@acme.mx', 'employee_number' => 'A-001']);
    $this->travel(1)->minutes();
    Log::spy();

    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->tenant, $employee->id]), employeeForm($this->plant, ['job_title' => 'Supervisora']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empleado actualizado.']]);

    expect(storedEmployee()->job_title)->toBe('Supervisora');
    Log::shouldHaveReceived('info')->with('employee.updated', Mockery::on(fn (array $context): bool => $context['employee_id'] === $employee->id && in_array('job_title', $context['changed'], true)));
});

it('deactivates and reactivates an employee, never deleting them', function (): void {
    $this->freezeSecond();
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    Log::spy();

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.deactivate', [$this->tenant, $employee->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empleado dado de baja.']]);

    expect(storedEmployee()->deactivated_at?->equalTo(now()))->toBeTrue();

    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $employee->id]))->assertConflict();
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.reactivate', [$this->tenant, $employee->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empleado reactivado.']]);

    expect(storedEmployee()->deactivated_at)->toBeNull();
    $this->actingAs($this->owner)->post(route('tenant.employees.reactivate', [$this->tenant, $employee->id]))->assertConflict();

    Log::shouldHaveReceived('info')->with('employee.deactivated', ['employee_id' => $employee->id]);
    Log::shouldHaveReceived('info')->with('employee.reactivated', ['employee_id' => $employee->id]);
});

it('does not edit a deactivated employee', function (): void {
    $employee = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    $this->actingAs($this->owner)->put(route('tenant.employees.update', [$this->tenant, $employee->id]), employeeForm($this->plant))->assertConflict();
});

it('answers 404 to an employee of another company', function (): void {
    $foreign = Employee::factory()->create(['name' => 'Ajeno']);

    $this->actingAs($this->owner)->put(route('tenant.employees.update', [$this->tenant, $foreign->id]), employeeForm($this->plant))->assertNotFound();
    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $foreign->id]))->assertNotFound();

    expect(Employee::query()->withoutGlobalScopes()->findOrFail($foreign->id)->only(['name', 'deactivated_at']))->toBe(['name' => 'Ajeno', 'deactivated_at' => null]);
});

it('lets hr change employees and refuses a viewer', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))
        ->post(route('tenant.employees.store', $this->tenant), employeeForm($this->plant))
        ->assertSessionHasNoErrors();

    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $employee = storedEmployee();

    $this->actingAs($viewer)->post(route('tenant.employees.store', $this->tenant), employeeForm($this->plant, ['email' => 'otro@acme.mx', 'employee_number' => '']))->assertForbidden();
    $this->actingAs($viewer)->put(route('tenant.employees.update', [$this->tenant, $employee->id]), employeeForm($this->plant))->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.employees.deactivate', [$this->tenant, $employee->id]))->assertForbidden();

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(storedEmployee()->deactivated_at)->toBeNull();
});
