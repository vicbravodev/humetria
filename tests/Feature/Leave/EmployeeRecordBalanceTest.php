<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Http\Controllers\EmployeeRecordController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\RecordPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(EmployeeRecordController::class, RecordPages::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->travelTo('2026-11-11 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->sara = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sara Gutiérrez', 'hired_on' => '2016-11-01']);
    $this->period = LeaveEntitlement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->sara->id, 'years_of_service' => 10, 'anniversary_on' => '2026-11-01', 'statutory_days' => 22, 'extra_days' => 3]);
});

function balanceEntry(LeaveEntitlement $period, int $days, string $at, string $kind = 'granted'): LeaveEntry
{
    return LeaveEntry::factory()->of($period)->create(['kind' => EntryKind::from($kind), 'days' => $days, 'created_at' => $at]);
}

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function balanceRecordProps(array $query = [], ?TenantRole $role = null): array
{
    $user = $role === null ? test()->hr : memberOf(test()->tenant, $role);

    return test()->actingAs($user)->get(route('tenant.vacations.employees.show', [test()->tenant, test()->sara, ...$query]))->assertOk()->inertiaProps();
}

it('gives each movement the balance the person had after it, so the account reads by itself', function (): void {
    balanceEntry($this->period, 22, '2026-11-11 15:00:00');
    balanceEntry($this->period, 3, '2026-11-11 15:00:01');
    balanceEntry($this->period, -2, '2026-11-11 15:00:02', 'taken');

    $rows = balanceRecordProps()['entries']['data'];

    expect(array_column($rows, 'days'))->toBe([-2, 3, 22])
        ->and(array_column($rows, 'balance'))->toBe([23, 25, 22]);
});

it('keeps the running balance across pages, and counts the days given in advance', function (): void {
    foreach (range(1, 12) as $second) {
        balanceEntry($this->period, 1, sprintf('2026-11-11 15:00:%02d', $second), 'opening');
    }
    LeaveEntry::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->sara->id, 'leave_entitlement_id' => null, 'kind' => EntryKind::Taken, 'days' => -2, 'created_at' => '2026-11-11 15:01:00']);

    $first = balanceRecordProps()['entries']['data'];
    $second = balanceRecordProps(['entries' => 2])['entries']['data'];

    expect($first[0]['balance'])->toBe(10)
        ->and($first[1]['balance'])->toBe(12)
        ->and($second[0]['balance'])->toBe(3)
        ->and($second[2]['balance'])->toBe(1);
});

it('never reads the ledger of another person or company', function (): void {
    balanceEntry($this->period, 5, '2026-11-11 15:00:00');
    $other = Tenant::factory()->create(['slug' => 'otra']);
    $stranger = Employee::factory()->create(['tenant_id' => $other->id]);
    $theirs = LeaveEntitlement::factory()->create(['tenant_id' => $other->id, 'employee_id' => $stranger->id]);
    LeaveEntry::factory()->of($theirs)->create(['days' => 99, 'created_at' => '2026-11-11 15:00:00']);
    $colleague = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $mine = LeaveEntitlement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $colleague->id, 'years_of_service' => 2]);
    LeaveEntry::factory()->of($mine)->create(['days' => 40, 'created_at' => '2026-11-11 14:00:00']);

    expect(balanceRecordProps()['entries']['data'][0]['balance'])->toBe(5);
});

it('gives whoever manages vacations the leave types to record an absence for the person, and a viewer none', function (): void {
    LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);

    expect(array_column(balanceRecordProps()['types'], 'name'))->toContain('Maternidad')
        ->and(balanceRecordProps([], TenantRole::Viewer)['types'])->toBe([]);
});
