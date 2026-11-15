<?php

declare(strict_types=1);

use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\CompanyReportingLines;
use App\Modules\Tenancy\Models\Tenant;

mutates(CompanyReportingLines::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->boss = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana']);
    $this->beto = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Beto']);
    $this->lines = resolve(ReportingLines::class);
});

it('tells the active people who share the direct manager of each person, without the person', function (): void {
    Employee::factory()->reportingTo($this->boss)->deactivated()->create(['name' => 'Luis']);
    $other = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $dan = Employee::factory()->reportingTo($other)->create(['name' => 'Dan']);
    $this->expectsDatabaseQueryCount(1);

    $peers = $this->lines->peersOf([$this->ana->id, $dan->id]);

    expect($peers[$this->ana->id])->toBe([$this->beto->id])
        ->and($peers)->not->toHaveKey($dan->id);
});

it('gives nobody for a person with no manager or with no peers, and never reads another company', function (): void {
    $alone = Employee::factory()->reportingTo($this->boss)->create();
    $solo = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $elsewhere = Tenant::factory()->create();
    $ghost = Employee::factory()->create(['tenant_id' => $elsewhere->id]);

    expect($this->lines->peersOf([$solo->id, $ghost->id, 'not-a-uuid']))->toBe([])
        ->and($this->lines->peersOf([]))->toBe([])
        ->and($this->lines->peersOf([$alone->id])[$alone->id])->toBe([$this->ana->id, $this->beto->id]);
});
