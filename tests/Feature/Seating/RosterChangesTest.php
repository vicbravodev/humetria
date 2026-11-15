<?php

declare(strict_types=1);

use App\Modules\Seating\Listeners\EndOnDeactivationOrMove;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;

mutates(EndOnDeactivationOrMove::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->warehouse = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana Ruiz']);
    $this->colleague = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->assignment = PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'position_id' => $this->position->id, 'starts_on' => '2026-01-10']);
    $this->colleagueAssignment = PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->colleague->id, 'position_id' => $this->position->id, 'starts_on' => '2026-01-10']);
});

function endOf(PositionAssignment $assignment): ?string
{
    workIn(test()->tenant);

    return PositionAssignment::query()->findOrFail($assignment->id)->ends_on?->toDateString();
}

it('ends the assignment when the person is deactivated, keeping the history and leaving the others', function (): void {
    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]), departure())->assertRedirect();

    expect(endOf($this->assignment))->toBe('2026-03-01')
        ->and(endOf($this->colleagueAssignment))->toBeNull();
});

it('ends the assignment when the person is moved to another center', function (): void {
    $this->actingAs($this->owner)->put(route('tenant.employees.update', [$this->tenant, $this->employee->id]), [
        'name' => $this->employee->name, 'email' => $this->employee->email, 'mobile' => $this->employee->mobile, 'gender' => $this->employee->gender->value,
        'work_center_id' => $this->warehouse->id, 'area' => 'Producción',
        'job_title' => $this->employee->job_title, 'employee_number' => $this->employee->employee_number,
    ])->assertSessionHasNoErrors();

    expect(endOf($this->assignment))->toBe('2026-03-01')
        ->and(endOf($this->colleagueAssignment))->toBeNull();
});

it('does not touch an assignment that already ended', function (): void {
    workIn($this->tenant);
    $this->assignment->update(['ends_on' => '2026-02-01']);

    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]), departure())->assertRedirect();

    expect(endOf($this->assignment))->toBe('2026-02-01');
});

it('does nothing for a person with no position', function (): void {
    $free = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $free->id]), departure())->assertRedirect();

    workIn($this->tenant);

    expect(PositionAssignment::query()->count())->toBe(2);
});
