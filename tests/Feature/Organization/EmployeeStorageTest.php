<?php

declare(strict_types=1);

use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

mutates(Employee::class, Area::class);

/**
 * The database guards areas and employees on its own, whatever path writes them.
 */
function rawEmployee(array $overrides = []): array
{
    $workCenter = WorkCenter::factory()->create();

    return [
        ...Employee::factory()->raw([
            'tenant_id' => $workCenter->tenant_id,
            'work_center_id' => $workCenter->id,
            'area_id' => Area::factory()->create(['tenant_id' => $workCenter->tenant_id])->id,
        ]),
        'id' => Str::uuid()->toString(),
        'gender' => 'female',
        ...$overrides,
    ];
}

it('stores ids as random UUID v4', function (): void {
    expect(Str::isUuid(Employee::factory()->create()->id, 4))->toBeTrue()
        ->and(Str::isUuid(Area::factory()->create()->id, 4))->toBeTrue();
});

it('places an employee in a work center and an area of the same company', function (): void {
    $employee = Employee::factory()->create();
    $row = DB::table('employees')->where('id', $employee->id)->sole();

    expect(DB::table('work_centers')->where('id', $row->work_center_id)->value('tenant_id'))->toBe($row->tenant_id)
        ->and(DB::table('areas')->where('id', $row->area_id)->value('tenant_id'))->toBe($row->tenant_id);
});

it('rejects a work center or an area of another company', function (string $column): void {
    $row = rawEmployee();
    $foreign = $column === 'work_center_id' ? WorkCenter::factory()->create()->id : Area::factory()->create()->id;

    DB::table('employees')->insert([...$row, $column => $foreign]);
})->throws(QueryException::class, 'foreign key')->with(['work_center_id', 'area_id']);

it('rejects an employee with neither email nor mobile', function (): void {
    DB::table('employees')->insert(rawEmployee(['email' => null, 'mobile' => null]));
})->throws(QueryException::class, 'employees_contact_present');

it('accepts an employee with only a mobile, or only an email', function (): void {
    DB::table('employees')->insert(rawEmployee(['email' => null, 'mobile' => '5512345678']));
    DB::table('employees')->insert(rawEmployee(['email' => 'ana@acme.mx', 'mobile' => null]));

    expect(DB::table('employees')->count())->toBe(2);
});

it('rejects a malformed field', function (array $overrides, string $constraint): void {
    DB::table('employees')->insert(rawEmployee($overrides));
})->throws(QueryException::class)->with([
    'mobile with 9 digits' => [['mobile' => '551234567'], 'employees_mobile_format'],
    'mobile with spaces' => [['mobile' => '55 1234 5678'], 'employees_mobile_format'],
    'uppercase email' => [['email' => 'Ana@acme.mx'], 'employees_email_lowercase'],
    'unknown gender' => [['gender' => 'x'], 'employees_gender_known'],
    'blank name' => [['name' => ' '], 'employees_name_filled'],
    'blank job title' => [['job_title' => ''], 'employees_job_title_filled'],
    'blank number' => [['employee_number' => ' '], 'employees_employee_number_filled'],
]);

it('rejects a second employee with the same email or number in the same company', function (string $column, string $value, string $other): void {
    $first = Employee::factory()->create([$column => $value]);
    $workCenter = WorkCenter::query()->withoutGlobalScopes()->findOrFail($first->work_center_id);

    Employee::factory()->create([
        'tenant_id' => $first->tenant_id,
        'work_center_id' => $workCenter->id,
        'area_id' => $first->area_id,
        $column => $other,
    ]);
})->throws(QueryException::class, 'unique')->with([
    'email' => ['email', 'ana@acme.mx', 'ana@acme.mx'],
    'number, whatever the case' => ['employee_number', 'a-001', 'A-001'],
]);

it('lets two companies use the same email and number', function (): void {
    Employee::factory()->create(['email' => 'ana@acme.mx', 'employee_number' => '001']);
    Employee::factory()->create(['email' => 'ana@acme.mx', 'employee_number' => '001']);

    expect(DB::table('employees')->count())->toBe(2);
});

it('rejects a second area with the same name in the same company, whatever the case', function (): void {
    $area = Area::factory()->create(['name' => 'Producción']);

    Area::factory()->create(['tenant_id' => $area->tenant_id, 'name' => 'PRODUCCIÓN']);
})->throws(QueryException::class, 'areas_tenant_name_unique');

it('casts the gender and the dates', function (): void {
    $employee = Employee::factory()->create(['gender' => Gender::Male, 'deactivated_at' => '2026-11-01 12:00:00+00']);
    workIn(Tenant::query()->findOrFail($employee->tenant_id));

    $read = Employee::query()->findOrFail($employee->id);

    expect($read->gender)->toBe(Gender::Male)
        ->and($read->deactivated_at?->toIso8601String())->toBe('2026-11-01T12:00:00+00:00');
});
