<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Http\Controllers\EmployeeRecordController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\RecordPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(EmployeeRecordController::class, RecordPages::class);

/** The queries of the record of a person, whatever the number of entries and requests (the session and the panel frame included). */
const LEAVE_RECORD_QUERIES = 31;

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    // Noon in Mexico City.
    $this->travelTo('2026-11-04 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
    $this->period = LeaveEntitlement::factory()->withGrant()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 3, 'anniversary_on' => '2026-03-15',
        'statutory_days' => 14, 'extra_days' => 2, 'enjoy_by' => '2026-09-15', 'prescribes_on' => '2027-09-15',
    ]);
});

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function leaveRecordProps(array $query = [], ?Employee $person = null): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.employees.show', [test()->tenant, $person ?? test()->ana, ...$query]))->assertOk()->inertiaProps();
}

it('lives under the vacations of the company and is hidden from a union', function (): void {
    expect(route('tenant.vacations.employees.show', [$this->tenant, $this->ana], absolute: false))->toBe("/admin/acme/vacations/employees/{$this->ana->id}");

    $union = Tenant::factory()->union()->create();

    $this->actingAs(memberOf($union, TenantRole::Owner))->get(route('tenant.vacations.employees.show', [$union, $this->ana]))->assertNotFound();
});

it('shows who the person is, their policy, start date and what they can ask for', function (): void {
    LeaveEntry::factory()->of($this->period)->taken(3)->create();
    LeaveEntry::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => null, 'kind' => EntryKind::Taken, 'days' => -2]);

    $props = leaveRecordProps();

    expect($props['person'])->toMatchArray(['id' => $this->ana->id, 'name' => 'Ana Solís', 'active' => true, 'policy' => 'Ley', 'startedOn' => '2024-03-15'])
        ->and($props['balance'])->toBe(['available' => 11, 'advanced' => 2])
        ->and($props['canManage'])->toBeTrue();
});

it('shows each period with its window, what is left and whether it may prescribe', function (): void {
    $old = LeaveEntitlement::factory()->withGrant()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2025-03-15',
        'statutory_days' => 12, 'extra_days' => 0, 'enjoy_by' => '2025-09-15', 'prescribes_on' => '2026-09-15',
    ]);
    $empty = LeaveEntitlement::factory()->withGrant()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2024-03-15',
        'statutory_days' => 6, 'extra_days' => 0, 'enjoy_by' => '2024-09-15', 'prescribes_on' => '2025-09-15',
    ]);
    LeaveEntry::factory()->of($empty)->taken(6)->create();

    $periods = collect(leaveRecordProps()['periods'])->keyBy('year');

    expect($periods->keys()->all())->toBe([2026, 2025, 2024])
        ->and($periods[2026])->toMatchArray(['id' => $this->period->id, 'granted' => 16, 'available' => 16, 'enjoyBy' => '2026-09-15', 'prescribesOn' => '2027-09-15', 'mayPrescribe' => false])
        ->and($periods[2025])->toMatchArray(['id' => $old->id, 'available' => 12, 'mayPrescribe' => true])
        // Nothing left: nothing to prescribe.
        ->and($periods[2024])->toMatchArray(['available' => 0, 'mayPrescribe' => false]);
});

it('pages the ledger and the requests with their own page names', function (): void {
    foreach (range(1, 12) as $day) {
        LeaveEntry::factory()->of($this->period)->create(['kind' => EntryKind::Adjustment, 'days' => 1, 'note' => "Ajuste {$day}", 'recorded_by' => $this->hr->id]);
        LeaveRequest::factory()->taken()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2026-02-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT), 'ends_on' => '2026-02-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT), 'working_days' => 1]);
    }

    $props = leaveRecordProps(['entries' => 2]);

    expect($props['entries']['per_page'])->toBe(10)->and($props['entries']['current_page'])->toBe(2)->and($props['entries']['total'])->toBe(14)->and($props['entries']['data'])->toHaveCount(4)
        ->and($props['requests']['current_page'])->toBe(1)->and($props['requests']['total'])->toBe(12)->and($props['requests']['data'])->toHaveCount(10)
        ->and($props['requests']['next_page_url'])->toContain('entries=2')->toContain('requests=2');
});

it('shows each entry of the ledger with its kind, tranche, period and note', function (): void {
    $entry = LeaveEntry::factory()->of($this->period)->create(['kind' => EntryKind::Adjustment, 'days' => -1, 'note' => 'Corrección de RH', 'recorded_by' => $this->hr->id]);

    $row = collect(leaveRecordProps()['entries']['data'])->firstWhere('id', $entry->id);

    expect($row)->toMatchArray(['kind' => 'adjustment', 'kindLabel' => 'Ajuste de RH', 'tranche' => 'statutory', 'days' => -1, 'period' => 2026, 'note' => 'Corrección de RH']);
});

it('shows who approved each request and when, and the leave type only to who manages vacations', function (): void {
    $boss = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz']);
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'decided_by_employee_id' => $boss->id, 'decided_at' => '2026-10-20 18:00:00', 'starts_on' => '2026-12-07', 'ends_on' => '2026-12-08', 'working_days' => 2, 'note' => 'Mi nota']);
    LeaveRequest::factory()->approved()->ofType($type)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'decided_by_employee_id' => null, 'decided_by_user_id' => $this->hr->id, 'decided_at' => '2026-10-21 18:00:00', 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-14', 'working_days' => 1]);

    $rows = collect(leaveRecordProps()['requests']['data'])->keyBy('startsOn');

    expect($rows['2026-12-07'])->toMatchArray(['type' => null, 'decidedBy' => 'Beto Ruiz', 'decidedOn' => '2026-10-20', 'note' => 'Mi nota', 'status' => 'approved'])
        ->and($rows['2026-12-14'])->toMatchArray(['type' => 'Maternidad', 'decidedBy' => 'RH', 'decidedOn' => '2026-10-21']);

    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $seen = $this->actingAs($viewer)->get(route('tenant.vacations.employees.show', [$this->tenant, $this->ana]))->assertOk()->inertiaProps();
    $rows = collect($seen['requests']['data'])->keyBy('startsOn');

    expect($rows['2026-12-14']['type'])->toBe('Permiso')
        ->and($rows['2026-12-07']['note'])->toBeNull()
        ->and($seen['canManage'])->toBeFalse();
});

it('keeps the notes of the ledger from a viewer and holds no answers nor amounts', function (): void {
    LeaveEntry::factory()->of($this->period)->create(['kind' => EntryKind::Adjustment, 'days' => 1, 'note' => 'Nota de RH', 'recorded_by' => $this->hr->id]);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $props = $this->actingAs($viewer)->get(route('tenant.vacations.employees.show', [$this->tenant, $this->ana]))->assertOk()->inertiaProps();

    expect($props['entries']['data'][0]['note'])->toBeNull()
        ->and(json_encode($props))->not->toContain('Nota de RH')->not->toContain('salary')->not->toContain('amount');
});

it('shows the statements with their acknowledgment', function (): void {
    LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => $this->period->id, 'issued_at' => '2026-03-15 18:00:00', 'acknowledged_at' => null]);

    expect(leaveRecordProps()['statements'][0])->toMatchArray(['year' => 2026, 'issuedOn' => '2026-03-15', 'acknowledgedOn' => null]);

    LeaveStatement::query()->update(['acknowledged_at' => '2026-03-20 18:00:00']);

    expect(leaveRecordProps()['statements'][0]['acknowledgedOn'])->toBe('2026-03-20');
});

it('still opens for a person who left, with their termination to settle', function (): void {
    $this->ana->update(['deactivated_at' => '2026-10-30 18:00:00']);
    $termination = LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'terminated_on' => '2026-10-30', 'pending_days' => 16, 'proportional_days' => 5.5]);

    $props = leaveRecordProps();

    expect($props['person']['active'])->toBeFalse()
        ->and($props['termination'])->toBe(['id' => $termination->id, 'terminatedOn' => '2026-10-30', 'pendingDays' => 16, 'proportionalDays' => 5.5, 'settledOn' => null, 'canSettle' => true]);
});

it('says nothing of a termination that was voided and marks one that was settled', function (): void {
    LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'voided_at' => now()]);

    expect(leaveRecordProps()['termination'])->toBeNull();

    $settled = LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'settled_at' => '2026-11-01 18:00:00', 'settled_by' => $this->hr->id]);

    expect(leaveRecordProps()['termination'])->toMatchArray(['id' => $settled->id, 'settledOn' => '2026-11-01', 'canSettle' => false]);
});

it('does not let a viewer settle', function (): void {
    LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $props = $this->actingAs($viewer)->get(route('tenant.vacations.employees.show', [$this->tenant, $this->ana]))->assertOk()->inertiaProps();

    expect($props['termination']['canSettle'])->toBeFalse();
});

it('is closed to whoever cannot view vacations', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(route('tenant.vacations.employees.show', [$this->tenant, $this->ana]))->assertForbidden();
});

it('is a 404 for a person that does not exist or belongs to another company', function (): void {
    $other = Tenant::factory()->create(['slug' => 'otra']);
    $stranger = Employee::factory()->create(['tenant_id' => $other->id]);
    $this->actingAs($this->hr);

    $this->get(route('tenant.vacations.employees.show', [$this->tenant, $stranger->id]))->assertNotFound();
    $this->get(route('tenant.vacations.employees.show', [$this->tenant, '3f2b8c1e-9a44-4d6e-8f10-0c2d5b7a1e11']))->assertNotFound();
    $this->get('/admin/acme/vacations/employees/not-a-uuid')->assertNotFound();
});

it('asks the same queries whatever the number of entries and requests', function (int $more): void {
    foreach (range(1, $more) as $day) {
        LeaveEntry::factory()->of($this->period)->create(['kind' => EntryKind::Adjustment, 'days' => 1, 'note' => 'x', 'recorded_by' => $this->hr->id]);
        LeaveRequest::factory()->taken()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2026-01-05', 'ends_on' => '2026-01-05', 'working_days' => 1]);
    }
    $this->actingAs($this->hr);

    $this->expectsDatabaseQueryCount(LEAVE_RECORD_QUERIES);
    $this->get(route('tenant.vacations.employees.show', [$this->tenant, $this->ana]))->assertOk();
})->with(['a few' => [5], 'many more' => [25]]);
