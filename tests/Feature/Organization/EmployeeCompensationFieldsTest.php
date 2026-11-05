<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\CreateEmployee;
use App\Modules\Organization\Actions\UpdateEmployee;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Http\Controllers\EmployeeController;
use App\Modules\Organization\Http\Requests\EmployeeRequest;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Organization\Support\EmployeeAttributes;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(EmployeeAttributes::class, StaffGroup::class, EmployeeRequest::class, CreateEmployee::class, UpdateEmployee::class, EmployeeController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
});

/** The form with everything a company account must give, plus what the pay scale uses. */
function payScaleForm(WorkCenter $workCenter, array $overrides = []): array
{
    return [
        'name' => 'Ana López', 'email' => 'ana@acme.mx', 'mobile' => '', 'gender' => 'female', 'work_center_id' => $workCenter->id,
        'area' => 'Producción', 'job_title' => 'Operadora', 'employee_number' => 'A-001',
        'job_category' => 'Operador', 'job_level' => 'A', 'hired_on' => '2024-03-15', 'staff_group' => 'unionized', 'state' => 'QUE',
        ...$overrides,
    ];
}

function theEmployee(): Employee
{
    return Employee::query()->withoutGlobalScopes()->sole();
}

it('keeps the category and level, hire date, group and state of a new employee, creating the job grade the first time', function (): void {
    Log::spy();

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), payScaleForm($this->plant))
        ->assertSessionHasNoErrors();

    $grade = JobGrade::query()->withoutGlobalScopes()->sole();
    $employee = theEmployee();

    expect($grade->only(['tenant_id', 'category', 'level']))->toBe(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A'])
        ->and($employee->job_grade_id)->toBe($grade->id)
        ->and($employee->hired_on?->toDateString())->toBe('2024-03-15')
        ->and($employee->staff_group)->toBe(StaffGroup::Unionized)
        ->and($employee->state)->toBe('QUE');
    Log::shouldHaveReceived('info', ['job_grade.created', ['job_grade_id' => $grade->id]])->once();
});

it('reuses the job grade the company has, whatever the case, and never another company\'s', function (): void {
    $mine = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    JobGrade::factory()->create(['category' => 'Operador', 'level' => 'A']);

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), payScaleForm($this->plant, ['job_category' => ' OPERADOR ', 'job_level' => 'a']))
        ->assertSessionHasNoErrors();

    expect(theEmployee()->job_grade_id)->toBe($mine->id)
        ->and(JobGrade::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('takes all of them as optional: a form without them stores nothing and creates no job grade', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), payScaleForm($this->plant, ['job_category' => '', 'job_level' => '', 'hired_on' => '', 'staff_group' => '', 'state' => '']))
        ->assertSessionHasNoErrors();

    expect(theEmployee()->only(['job_grade_id', 'hired_on', 'staff_group', 'state']))->toBe(['job_grade_id' => null, 'hired_on' => null, 'staff_group' => null, 'state' => null])
        ->and(JobGrade::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('changes them when correcting an employee, and clears them when they are sent empty', function (): void {
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'employee_number' => 'A-001', 'email' => 'ana@acme.mx']);
    $this->travel(1)->minutes();
    Log::spy();

    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->tenant, $employee->id]), payScaleForm($this->plant, ['staff_group' => 'trusted']))
        ->assertSessionHasNoErrors();

    expect(theEmployee()->staff_group)->toBe(StaffGroup::Trusted);
    Log::shouldHaveReceived('info')->with('employee.updated', Mockery::on(fn (array $context): bool => array_values(array_intersect($context['changed'], ['hired_on', 'job_grade_id', 'staff_group', 'state'])) === ['hired_on', 'job_grade_id', 'staff_group', 'state']));

    $this->actingAs($this->owner)
        ->put(route('tenant.employees.update', [$this->tenant, $employee->id]), payScaleForm($this->plant, ['job_category' => '', 'job_level' => '', 'hired_on' => '', 'staff_group' => '', 'state' => '']))
        ->assertSessionHasNoErrors();

    expect(theEmployee()->only(['job_grade_id', 'hired_on', 'staff_group', 'state']))->toBe(['job_grade_id' => null, 'hired_on' => null, 'staff_group' => null, 'state' => null])
        ->and(JobGrade::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('validates the pay scale fields', function (array $overrides, string $field, string $message): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), payScaleForm($this->plant, $overrides))
        ->assertSessionHasErrors([$field => $message]);

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(JobGrade::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([
    'category without level' => [['job_level' => ''], 'job_level', 'El campo nivel es obligatorio cuando categoría esté presente.'],
    'level without category' => [['job_category' => ''], 'job_category', 'El campo categoría es obligatorio cuando nivel esté presente.'],
    'long category' => [['job_category' => str_repeat('a', 121)], 'job_category', 'El campo categoría no debe tener más de 120 caracteres.'],
    'long level' => [['job_level' => str_repeat('a', 61)], 'job_level', 'El campo nivel no debe tener más de 60 caracteres.'],
    'not a date' => [['hired_on' => 'ayer'], 'hired_on', 'Escribe una fecha de ingreso válida.'],
    'impossible date' => [['hired_on' => '2024-02-30'], 'hired_on', 'Escribe una fecha de ingreso válida.'],
    'unknown group' => [['staff_group' => 'directivo'], 'staff_group', 'Elige un grupo de la lista.'],
    'unknown state' => [['state' => 'XXX'], 'state', 'Elige un estado de la lista.'],
]);

it('refuses a hire date in the future, counting today in Mexico City', function (): void {
    // 21:00 of Nov 3 in Mexico City is already Nov 4 in UTC.
    $this->travelTo('2026-11-04 03:00:00');

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), payScaleForm($this->plant, ['hired_on' => '2026-11-04']))
        ->assertSessionHasErrors(['hired_on' => 'La fecha de ingreso no puede ser futura.']);

    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), payScaleForm($this->plant, ['hired_on' => '2026-11-03']))
        ->assertSessionHasNoErrors();
});

it('keeps requiring name, contact, gender, work center and area of a company', function (array $overrides, string $field): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.employees.store', $this->tenant), payScaleForm($this->plant, $overrides))
        ->assertSessionHasErrors($field);

    expect(Employee::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([
    'no contact' => [['email' => '', 'mobile' => ''], 'email'],
    'no gender' => [['gender' => ''], 'gender'],
    'no work center' => [['work_center_id' => ''], 'work_center_id'],
    'no area' => [['area' => ''], 'area'],
    'no name' => [['name' => ''], 'name'],
    'no job title' => [['job_title' => ''], 'job_title'],
]);

it('lists the pay scale fields of each employee and the catalogs to choose from', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Con', 'job_grade_id' => $grade->id, 'hired_on' => '2024-03-15', 'staff_group' => 'trusted', 'state' => 'QUE']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Sin']);

    $this->actingAs($this->owner)
        ->get(route('tenant.employees.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.job_category', 'Operador')
            ->where('employees.data.0.job_level', 'A')
            ->where('employees.data.0.hired_on', '2024-03-15')
            ->where('employees.data.0.staff_group', 'trusted')
            ->where('employees.data.0.state', 'QUE')
            ->where('employees.data.1.job_category', '')
            ->where('employees.data.1.job_level', '')
            ->where('employees.data.1.hired_on', null)
            ->where('employees.data.1.staff_group', '')
            ->where('employees.data.1.state', '')
            ->where('staff_groups', [['value' => 'unionized', 'label' => 'Sindicalizado'], ['value' => 'trusted', 'label' => 'Confianza']])
            ->has('states', 32)
            ->where('states.0', ['value' => 'AGU', 'label' => 'Aguascalientes']));
});

it('names the staff group as a spreadsheet may write it', function (string $text, ?string $group): void {
    expect(StaffGroup::fromSpanish($text)?->value)->toBe($group);
})->with([
    'sindicalizado' => ['Sindicalizado', 'unionized'],
    'feminine, spaced' => [' sindicalizada ', 'unionized'],
    'plural, uppercase' => ['SINDICALIZADOS', 'unionized'],
    'confianza' => ['Confianza', 'trusted'],
    'de confianza' => ['de confianza', 'trusted'],
    'unknown' => ['Directivo', null],
    'empty' => ['', null],
]);

it('keeps an employee\'s job grade inside their own company, in the database', function (): void {
    $foreign = JobGrade::factory()->create();

    expect(fn () => DB::transaction(fn () => Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'job_grade_id' => $foreign->id])))
        ->toThrow(QueryException::class, 'employees_job_grade_id_tenant_id_foreign');
});

it('does not delete a job grade an employee has', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'job_grade_id' => $grade->id]);

    expect(fn () => DB::transaction(fn () => DB::table('job_grades')->where('id', $grade->id)->delete()))->toThrow(QueryException::class);
});

it('accepts only the known staff groups, in the database', function (): void {
    $raw = Employee::factory()->raw(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'gender' => 'female']);

    expect(fn () => DB::transaction(fn () => DB::table('employees')->insert([...$raw, 'id' => (string) Str::uuid(), 'staff_group' => 'directivo'])))
        ->toThrow(QueryException::class, 'employees_staff_group_known');
});
