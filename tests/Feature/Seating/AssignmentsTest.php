<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Seating\Actions\AssignPeople;
use App\Modules\Seating\Actions\UnassignPerson;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AssignPeople::class, UnassignPerson::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->other = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    $this->beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Beto Pérez']);
});

function assign(array $employeeIds, ?StandingPosition $position = null): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.seating.positions.assignments.store', [test()->tenant, ($position ?? test()->position)->id]), ['employee_ids' => $employeeIds]);
}

it('assigns people of the center to a position from today, with the audit and the log', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    assign([$this->ana->id, $this->beto->id])->assertRedirect()->assertSessionHasNoErrors();

    $rows = PositionAssignment::query()->orderBy('employee_id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('position_id')->unique()->all())->toBe([$this->position->id])
        ->and($rows->first()?->starts_on->toDateString())->toBe('2026-03-01')
        ->and($rows->first()?->ends_on)->toBeNull();
    $entry = AuditEntry::query()->where('event', 'seating.people_assigned')->sole();
    expect($entry->metadata)->toEqual(['position_id' => $this->position->id, 'count' => 2]);
    Log::shouldHaveReceived('info')->with('seating.people_assigned', ['position_id' => $this->position->id, 'count' => 2])->once();
});

it('is idempotent for a person already in the same position', function (): void {
    $this->actingAs($this->hr);
    assign([$this->ana->id])->assertSessionHasNoErrors();

    assign([$this->ana->id, $this->beto->id])->assertSessionHasNoErrors();

    expect(PositionAssignment::query()->count())->toBe(2);
});

it('refuses people who are not active in the position center', function (string $who): void {
    $outsider = match ($who) {
        'another center' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->other->id]),
        'deactivated' => EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]),
        'another company' => EmployeeFactory::new()->create(),
    };
    $this->actingAs($this->hr);

    assign([$outsider->id])->assertSessionHasErrors('employee_ids.0');

    expect(PositionAssignment::query()->count())->toBe(0);
})->with(['another center', 'deactivated', 'another company']);

it('refuses a second current position for the same person', function (): void {
    $second = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Repartidor']);
    $this->actingAs($this->hr);
    assign([$this->ana->id])->assertSessionHasNoErrors();

    assign([$this->ana->id], $second)->assertSessionHasErrors('employee_ids.0');

    expect(PositionAssignment::query()->where('position_id', $second->id)->count())->toBe(0);
});

it('refuses in the database a second current position and an end before the start', function (): void {
    $second = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);

    expect(fn () => DB::transaction(fn () => PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $second->id])))
        ->toThrow(QueryException::class, 'position_assignments_one_current')
        ->and(fn () => DB::transaction(fn () => DB::table('position_assignments')->update(['ends_on' => '2020-01-01'])))
        ->toThrow(QueryException::class, 'position_assignments_end_not_before_start');
});

it('ends an assignment instead of deleting it, and lets the person take another position afterwards', function (): void {
    Log::spy();
    $second = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $assignment = PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'starts_on' => '2026-01-10']);
    $this->actingAs($this->hr);

    $this->delete(route('tenant.seating.assignments.destroy', [$this->tenant, $assignment->id]))->assertRedirect();

    expect($assignment->fresh()?->ends_on?->toDateString())->toBe('2026-03-01')
        ->and(PositionAssignment::query()->count())->toBe(1);
    assign([$this->ana->id], $second)->assertSessionHasNoErrors();
    expect(PositionAssignment::query()->count())->toBe(2);
    $entry = AuditEntry::query()->where('event', 'seating.person_unassigned')->sole();
    expect($entry->metadata)->toEqual(['position_id' => $this->position->id, 'employee_id' => $this->ana->id]);
    Log::shouldHaveReceived('info')->with('seating.person_unassigned', ['position_id' => $this->position->id, 'employee_id' => $this->ana->id])->once();
});

it('answers 404 when ending an assignment that is already over or is not found', function (): void {
    $over = PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'starts_on' => '2026-01-10', 'ends_on' => '2026-02-01']);
    $this->actingAs($this->hr);

    $this->delete(route('tenant.seating.assignments.destroy', [$this->tenant, $over->id]))->assertNotFound();
    $this->delete(route('tenant.seating.assignments.destroy', [$this->tenant, (string) Illuminate\Support\Str::uuid()]))->assertNotFound();
    expect($over->fresh()?->ends_on?->toDateString())->toBe('2026-02-01');
});

it('lets only manage-seating assign and unassign', function (TenantRole $role): void {
    $assignment = PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);
    $user = memberOf($this->tenant, $role);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    assign([$this->beto->id])->assertForbidden();
    $this->delete(route('tenant.seating.assignments.destroy', [$this->tenant, $assignment->id]))->assertForbidden();
    $this->get(route('tenant.seating.positions.employees', [$this->tenant, $this->position->id]))->assertForbidden();

    expect(PositionAssignment::query()->count())->toBe(1)->and($assignment->fresh()?->ends_on)->toBeNull();
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('lists the people of the center only, with server-side search ignoring case and accents', function (): void {
    EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->other->id, 'name' => 'Ángela Otra']);
    EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ángela Baja']);
    EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ángela Díaz']);
    $this->actingAs($this->hr);

    $all = $this->getJson(route('tenant.seating.positions.employees', [$this->tenant, $this->position->id]))->assertOk()->json();
    $found = $this->getJson(route('tenant.seating.positions.employees', [$this->tenant, $this->position->id, 'q' => 'angela']))->assertOk()->json();

    expect(array_column($all, 'label'))->toEqualCanonicalizing(['Ana Ruiz', 'Ángela Díaz', 'Beto Pérez'])
        ->and(array_column($found, 'label'))->toBe(['Ángela Díaz'])
        ->and(array_keys($found[0]))->toBe(['value', 'label']);
});

it('pages the people of a position on the server', function (): void {
    foreach (EmployeeFactory::new()->count(17)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]) as $employee) {
        PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'position_id' => $this->position->id]);
    }
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-02-01']);
    $this->actingAs($this->hr);

    $this->get(route('tenant.seating.positions.show', [$this->tenant, $this->position->id]))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 17)->where('people.per_page', 15)->has('people.data', 15)
            ->where('people.data.0', fn ($row) => array_keys($row->toArray()) === ['assignmentId', 'employeeId', 'name', 'area', 'startsOn']));
    $this->get(route('tenant.seating.positions.show', [$this->tenant, $this->position->id, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('people.data', 2));
});
