<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The database keeps the reporting line sound on its own, whatever path writes it: the tests write with plain SQL.
 */
function reportsTo(Employee $employee, ?Employee $manager): void
{
    // In a transaction of its own, so a rejected write leaves the rows readable afterwards.
    DB::transaction(fn () => DB::table('employees')->where('id', $employee->id)->update(['manager_id' => $manager?->id]));
}

function managerIdOf(Employee $employee): ?string
{
    return DB::table('employees')->where('id', $employee->id)->value('manager_id');
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    // Ana reports to Beto, who reports to Carla, who is at the top.
    [$this->ana, $this->beto, $this->carla] = Employee::factory()->count(3)->create(['tenant_id' => $this->tenant->id])->all();
    reportsTo($this->ana, $this->beto);
    reportsTo($this->beto, $this->carla);
});

it('stores a person with no manager and no recognized seniority', function (): void {
    $row = DB::table('employees')->where('id', $this->carla->id)->sole();

    expect($row->manager_id)->toBeNull()
        ->and($row->seniority_on)->toBeNull();
});

it('rejects a person as their own manager', function (): void {
    reportsTo($this->ana, $this->ana);
})->throws(QueryException::class, 'employees_manager_not_self');

it('rejects a manager who reports, directly or not, to the person, and changes nothing', function (): void {
    expect(fn () => reportsTo($this->carla, $this->ana))->toThrow(QueryException::class, 'employees_manager_cycle');

    expect(managerIdOf($this->ana))->toBe($this->beto->id)
        ->and(managerIdOf($this->beto))->toBe($this->carla->id)
        ->and(managerIdOf($this->carla))->toBeNull();
});

it('rejects a manager of another company', function (): void {
    reportsTo($this->carla, Employee::factory()->create());
})->throws(QueryException::class, 'foreign key');

it('moves a person to another manager when no cycle forms', function (): void {
    reportsTo($this->ana, $this->carla);

    expect(managerIdOf($this->ana))->toBe($this->carla->id);
});

it('never deletes a manager from under their reports', function (): void {
    DB::table('employees')->where('id', $this->carla->id)->delete();
})->throws(QueryException::class, 'foreign key');

it('returns the recognized seniority as a date with no time', function (): void {
    workIn($this->tenant);
    $this->ana->update(['seniority_on' => '2019-01-10']);

    $seniority = Employee::query()->findOrFail($this->ana->id)->seniority_on;

    expect($seniority)->toBeInstanceOf(CarbonImmutable::class)
        ->and($seniority?->toDateTimeString())->toBe('2019-01-10 00:00:00');
});

it('relates a person to their manager and a manager to their reports', function (): void {
    workIn($this->tenant);
    $dora = Employee::factory()->reportingTo($this->beto)->create();

    expect($dora->tenant_id)->toBe($this->tenant->id)
        ->and($dora->manager()->sole()->id)->toBe($this->beto->id)
        ->and($this->beto->reports()->orderBy('name')->pluck('id')->all())->toEqualCanonicalizing([$this->ana->id, $dora->id])
        ->and($this->carla->manager()->first())->toBeNull();
});
