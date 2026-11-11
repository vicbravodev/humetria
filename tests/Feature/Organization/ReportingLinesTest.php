<?php

declare(strict_types=1);

use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\CompanyReportingLines;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

mutates(CompanyReportingLines::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    // Dir is at the top; Gte reports to Dir, Jefa to Gte, and Ana and Luis (who left) to Jefa.
    $this->dir = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->gte = Employee::factory()->reportingTo($this->dir)->create();
    $this->jefa = Employee::factory()->reportingTo($this->gte)->create();
    $this->ana = Employee::factory()->reportingTo($this->jefa)->create(['name' => 'Ana']);
    $this->luis = Employee::factory()->reportingTo($this->jefa)->deactivated()->create(['name' => 'Luis']);
    $this->lines = resolve(ReportingLines::class);
});

it('tells the direct manager of a person', function (): void {
    $this->expectsDatabaseQueryCount(1);

    expect($this->lines->managerOf($this->ana->id))->toBe($this->jefa->id);
});

it('tells the chain above a person, from the direct manager up', function (): void {
    $this->expectsDatabaseQueryCount(1);

    expect($this->lines->chainOf($this->ana->id))->toBe([$this->jefa->id, $this->gte->id, $this->dir->id]);
});

it('tells the active direct reports of a person, by name', function (): void {
    $zoe = Employee::factory()->reportingTo($this->jefa)->create(['name' => 'Zoe']);
    $beto = Employee::factory()->reportingTo($this->jefa)->create(['name' => 'Beto']);
    $this->expectsDatabaseQueryCount(1);

    expect($this->lines->directReportIds($this->jefa->id))->toBe([$this->ana->id, $beto->id, $zoe->id]);
});

it('tells everyone active below a person, without the person', function (): void {
    $this->expectsDatabaseQueryCount(1);

    expect($this->lines->subtreeIds($this->dir->id))->toEqualCanonicalizing([$this->gte->id, $this->jefa->id, $this->ana->id]);
});

it('keeps a manager who left in the chain, and their reports in the line', function (): void {
    $this->gte->update(['deactivated_at' => now()]);

    expect($this->lines->subtreeIds($this->dir->id))->toEqualCanonicalizing([$this->jefa->id, $this->ana->id])
        ->and($this->lines->chainOf($this->ana->id))->toBe([$this->jefa->id, $this->gte->id, $this->dir->id])
        ->and($this->lines->managerOf($this->luis->id))->toBe($this->jefa->id);
});

it('answers nothing for a person with no manager and nobody below', function (): void {
    expect($this->lines->managerOf($this->dir->id))->toBeNull()
        ->and($this->lines->chainOf($this->dir->id))->toBe([])
        ->and($this->lines->directReportIds($this->ana->id))->toBe([])
        ->and($this->lines->subtreeIds($this->ana->id))->toBe([]);
});

it('answers nothing about a person of another company', function (): void {
    $boss = Employee::factory()->create();
    $other = Employee::factory()->reportingTo($boss)->create();

    expect($this->lines->managerOf($other->id))->toBeNull()
        ->and($this->lines->chainOf($other->id))->toBe([])
        ->and($this->lines->directReportIds($boss->id))->toBe([])
        ->and($this->lines->subtreeIds($boss->id))->toBe([]);
});

it('ends on a cycle already in the data, without repeating anyone or the person', function (): void {
    // A restore with the trigger disabled: Ana reports to Jefa and Jefa to Ana.
    DB::statement('ALTER TABLE employees DISABLE TRIGGER employees_manager_acyclic');
    DB::table('employees')->where('id', $this->jefa->id)->update(['manager_id' => $this->ana->id]);
    DB::statement('ALTER TABLE employees ENABLE TRIGGER employees_manager_acyclic');

    expect($this->lines->chainOf($this->ana->id))->toBe([$this->jefa->id])
        ->and($this->lines->subtreeIds($this->ana->id))->toBe([$this->jefa->id]);
});
