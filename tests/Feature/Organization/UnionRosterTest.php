<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\CreateEmployee;
use App\Modules\Organization\Actions\UpdateEmployee;
use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Http\Requests\EmployeeRequest;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Enums\TenantType;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

mutates(EmployeeRequest::class, CreateEmployee::class, UpdateEmployee::class);

beforeEach(function (): void {
    $this->union = Tenant::factory()->union()->create();
    $this->owner = ownerOf($this->union);
});

function unionEmployee(): Employee
{
    return Employee::query()->withoutGlobalScopes()->sole();
}

it('knows whether the current account is a union', function (): void {
    workIn($this->union);

    expect(resolve(Companies::class)->current()->isUnion())->toBeTrue()
        ->and((new CompanySummary('x', 'X', 'x', false, null, TenantType::Company))->isUnion())->toBeFalse();
});

it('adds a person to a union with only the company ID', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->union), ['employee_number' => 'E-001'])
        ->assertSessionHasNoErrors();

    $employee = unionEmployee();

    expect($employee->tenant_id)->toBe($this->union->id)
        ->and($employee->employee_number)->toBe('E-001')
        ->and([$employee->name, $employee->email, $employee->mobile, $employee->gender, $employee->work_center_id, $employee->area_id, $employee->job_title])->each->toBeNull();
});

it('keeps everything a union person may carry', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->union), [
            'employee_number' => 'E-001', 'name' => 'Ana López', 'job_title' => 'Operadora', 'area' => 'Producción',
            'job_category' => 'Operador', 'job_level' => 'A', 'hired_on' => '2024-03-15', 'staff_group' => 'unionized', 'state' => 'QUE',
        ])
        ->assertSessionHasNoErrors();

    $employee = unionEmployee();

    expect($employee->name)->toBe('Ana López')
        ->and($employee->job_title)->toBe('Operadora')
        ->and(Area::query()->withoutGlobalScopes()->sole()->name)->toBe('Producción')
        ->and($employee->area_id)->toBe(Area::query()->withoutGlobalScopes()->sole()->id)
        ->and(JobGrade::query()->withoutGlobalScopes()->sole()->only(['category', 'level']))->toBe(['category' => 'Operador', 'level' => 'A'])
        ->and($employee->hired_on?->toDateString())->toBe('2024-03-15')
        ->and($employee->staff_group?->value)->toBe('unionized')
        ->and($employee->state)->toBe('QUE');
});

it('requires the company ID of a union person', function (string $value): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->union), ['name' => 'Ana López', 'employee_number' => $value])
        ->assertSessionHasErrors(['employee_number' => 'El campo ID de la empresa es obligatorio.']);

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
})->with(['missing' => [''], 'blank' => ['   ']]);

it('rejects a repeated company ID in a union without caring about case, but lets the person keep their own', function (): void {
    Employee::factory()->create(['tenant_id' => $this->union->id, 'work_center_id' => null, 'area_id' => null, 'employee_number' => 'e-001']);
    $other = Employee::factory()->create(['tenant_id' => $this->union->id, 'work_center_id' => null, 'area_id' => null, 'employee_number' => 'E-002']);

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->union), ['employee_number' => 'E-001'])
        ->assertSessionHasErrors(['employee_number' => 'Ya hay una persona con este ID de la empresa.']);
    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->union, $other]), ['employee_number' => 'E-001'])
        ->assertSessionHasErrors('employee_number');
    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->union, $other]), ['employee_number' => 'E-002', 'name' => 'Beto'])
        ->assertSessionHasNoErrors();

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(2)
        ->and($other->refresh()->name)->toBe('Beto');
});

it('lets two accounts use the same company ID', function (): void {
    $company = Tenant::factory()->create();
    Employee::factory()->create(['tenant_id' => $company->id, 'employee_number' => 'E-001']);

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->union), ['employee_number' => 'E-001'])
        ->assertSessionHasNoErrors();

    expect(Employee::query()->withoutGlobalScopes()->where('tenant_id', $this->union->id)->count())->toBe(1);
});

it('never stores contact, gender or work center in a union, even when sent', function (): void {
    $plant = WorkCenter::factory()->for(Tenant::factory()->create())->create();

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->union), [
            'employee_number' => 'E-001', 'email' => 'ana@acme.mx', 'mobile' => '5512345678', 'gender' => 'female', 'work_center_id' => $plant->id,
        ])
        ->assertSessionHasNoErrors();

    $employee = unionEmployee();

    expect([$employee->email, $employee->mobile, $employee->gender, $employee->work_center_id])->each->toBeNull();
});

it('still validates the optional fields a union person carries', function (array $overrides, string $field): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->union), ['employee_number' => 'E-001', ...$overrides])
        ->assertSessionHasErrors($field);
})->with([
    'a category without level' => [['job_category' => 'Operador'], 'job_level'],
    'a bad group' => [['staff_group' => 'boss'], 'staff_group'],
    'an unknown state' => [['state' => 'ZZZ'], 'state'],
    'a future hire date' => [['hired_on' => '2999-01-01'], 'hired_on'],
    'a name with a line break' => [['name' => "Ana\nLópez"], 'name'],
]);

it('does not announce a union person to the evaluations, which need a work center', function (): void {
    Event::fake([EmployeeCreated::class, EmployeeMoved::class]);

    $this->actingAs($this->owner)->post(route('tenant.employees.store', $this->union), ['employee_number' => 'E-001', 'name' => 'Ana'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->put(route('tenant.employees.update', [$this->union, unionEmployee()]), ['employee_number' => 'E-001', 'name' => 'Ana López'])->assertSessionHasNoErrors();

    expect(unionEmployee()->name)->toBe('Ana López');
    Event::assertNotDispatched(EmployeeCreated::class);
    Event::assertNotDispatched(EmployeeMoved::class);
});

it('keeps requiring everything of a company, with the company ID still optional', function (): void {
    $company = Tenant::factory()->create();

    $this->actingAs(ownerOf($company))
        ->post(route('tenant.employees.store', $company), ['employee_number' => 'E-001'])
        ->assertSessionHasErrors(['name', 'email', 'gender', 'work_center_id', 'area', 'job_title']);

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('lists a union person by company ID when they have no name, with the union props only', function (): void {
    Employee::factory()->create(['tenant_id' => $this->union->id, 'work_center_id' => null, 'area_id' => null, 'gender' => null, 'name' => null, 'email' => null, 'mobile' => null, 'job_title' => null, 'employee_number' => 'E-001']);

    $this->actingAs($this->owner)
        ->get(route('tenant.employees.index', $this->union))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization/employees')
            ->where('tenant.type', 'union')
            ->where('employees.data.0.name', 'E-001')
            ->where('employees.data.0.employee_number', 'E-001')
            ->where('employees.data.0.work_center', '')
            ->where('employees.data.0.area', '')
            ->where('counts.active', 1));
});

it('lets a union search areas and job grades for the form', function (): void {
    Area::factory()->create(['tenant_id' => $this->union->id, 'name' => 'Producción']);

    $this->actingAs($this->owner)->getJson(route('tenant.areas.options', [$this->union, 'q' => 'prod']))->assertOk()->assertJsonCount(1);
    $this->actingAs($this->owner)->getJson(route('tenant.job-grades.options', [$this->union, 'category']))->assertOk();
});
