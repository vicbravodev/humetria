<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Contracts\CommitteeWork;
use App\Modules\Seating\Data\CommitteeTask;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\CompanyCommitteeWork;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(CompanyCommitteeWork::class);

// Rediseño v2 (board V2-Comision): what waits for the Comisión in Ley Silla, as the queue of every standard reads it.

it('gives the assessments to sign with whose they are, oldest first, then how many wait for the minutes, center by center', function (): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $north = WorkCenter::factory()->for($tenant)->create();
    $south = WorkCenter::factory()->for($tenant)->create();
    $draft = fn (WorkCenter $center, string $name, string $at) => RiskAssessment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'created_at' => $at, 'employee_id' => Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => $name])->id]);
    $draft($north, 'Óscar Peña', '2026-10-02');
    $draft($north, 'José Antonio', '2026-10-01');
    RiskAssessment::factory()->signed()->count(2)->create(['tenant_id' => $tenant->id, 'work_center_id' => $south->id]);
    RiskAssessment::factory()->signed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $south->id, 'recorded_in_document_id' => null]);
    $elsewhere = RiskAssessment::factory()->create();

    expect(app(CommitteeWork::class)->waiting([$north->id, $south->id, $elsewhere->work_center_id]))->toEqual([
        new CommitteeTask($north->id, 'signatures', 2, ['José Antonio', 'Óscar Peña']),
        new CommitteeTask($south->id, 'minutes', 3, []),
    ]);
});

it('gives the people who work standing and have no assessment, center by center and only where there are some', function (): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $north = WorkCenter::factory()->for($tenant)->create();
    $south = WorkCenter::factory()->for($tenant)->create();
    $where = fn (WorkCenter $center): array => ['tenant_id' => $tenant->id, 'work_center_id' => $center->id];
    $position = StandingPosition::factory()->create($where($north));

    foreach (range(1, 3) as $ignored) {
        PositionAssignment::factory()->create(['tenant_id' => $tenant->id, 'position_id' => $position->id, 'employee_id' => Employee::factory()->create($where($north))->id]);
    }

    $assessed = Employee::factory()->create($where($north));
    PositionAssignment::factory()->create(['tenant_id' => $tenant->id, 'position_id' => $position->id, 'employee_id' => $assessed->id]);
    RiskAssessment::factory()->signed()->create($where($north) + ['position_id' => $position->id, 'employee_id' => $assessed->id]);

    // The signed assessment of the fourth person has no minutes yet; the three others have none at all.
    expect(app(CommitteeWork::class)->waiting([$north->id, $south->id]))->toEqual([
        new CommitteeTask($north->id, 'minutes', 1, []),
        new CommitteeTask($north->id, 'unassessed', 3, []),
    ]);
});
