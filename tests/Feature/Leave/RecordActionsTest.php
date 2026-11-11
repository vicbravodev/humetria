<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Http\Controllers\EmployeeRecordController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Leave\Support\Balances;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(EmployeeRecordController::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->travelTo('2026-11-04 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
    $this->period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
});

/**
 * @param  array<string, mixed>  $data
 */
function leaveAdjustment(array $data = []): array
{
    return ['period' => test()->period->id, 'tranche' => 'statutory', 'days' => 2, 'note' => 'Antigüedad reconocida', ...$data];
}

it('lets RH adjust a tranche, which shows in the ledger and the audit trail', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.employees.adjust', [$this->tenant, $this->ana]), leaveAdjustment())->assertRedirect();

    $entry = LeaveEntry::query()->where('kind', EntryKind::Adjustment)->sole();

    expect($entry->days)->toBe(2)->and($entry->note)->toBe('Antigüedad reconocida')->and($entry->recorded_by)->toBe($this->hr->id)
        ->and(Balances::available($this->ana->id))->toBe(16)
        ->and(AuditEntry::query()->where('event', 'leave.balance_adjusted')->count())->toBe(1);

    $ledger = $this->actingAs($this->hr)->get(route('tenant.vacations.employees.show', [$this->tenant, $this->ana]))->inertiaProps()['entries']['data'];

    expect(collect($ledger)->firstWhere('id', $entry->id))->toMatchArray(['kindLabel' => 'Ajuste de RH', 'days' => 2]);
});

it('adjusts against the person too, but never below zero', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.employees.adjust', [$this->tenant, $this->ana]), leaveAdjustment(['days' => -4]))->assertRedirect();

    expect(Balances::available($this->ana->id))->toBe(10);

    $this->actingAs($this->hr)->post(route('tenant.vacations.employees.adjust', [$this->tenant, $this->ana]), leaveAdjustment(['days' => -11]))->assertSessionHasErrors('days');

    expect(Balances::available($this->ana->id))->toBe(10);
});

it('asks for the note, the days and a valid tranche', function (array $data, string $field): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.employees.adjust', [$this->tenant, $this->ana]), leaveAdjustment($data))->assertSessionHasErrors($field);

    expect(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->count())->toBe(0);
})->with([
    'no note' => [['note' => ''], 'note'],
    'blank note' => [['note' => '   '], 'note'],
    'zero days' => [['days' => 0], 'days'],
    'not a number' => [['days' => 'dos'], 'days'],
    'too many days' => [['days' => 1000], 'days'],
    'unknown tranche' => [['tranche' => 'bonus'], 'tranche'],
    'no period' => [['period' => ''], 'period'],
]);

it('does not adjust the period of somebody else', function (): void {
    $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz']);
    $other = Tenant::factory()->create();
    $foreign = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->hr);
    $this->post(route('tenant.vacations.employees.adjust', [$this->tenant, $beto]), leaveAdjustment())->assertNotFound();
    $this->post(route('tenant.vacations.employees.adjust', [$this->tenant, $this->ana]), leaveAdjustment(['period' => $foreign->id]))->assertNotFound();

    expect(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->count())->toBe(0);
});

it('lets only owner and hr adjust and settle', function (TenantRole $role, bool $allowed): void {
    $termination = LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->actingAs(memberOf($this->tenant, $role));

    $this->post(route('tenant.vacations.employees.adjust', [$this->tenant, $this->ana]), leaveAdjustment())->assertStatus($allowed ? 302 : 403);
    $this->post(route('tenant.vacations.terminations.settle', [$this->tenant, $termination]))->assertStatus($allowed ? 302 : 403);

    expect(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->count())->toBe($allowed ? 1 : 0)
        ->and(LeaveTermination::query()->whereNotNull('settled_at')->count())->toBe($allowed ? 1 : 0);
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
    'committee' => [TenantRole::Committee, false],
]);

it('settles the days of a person who left, once', function (): void {
    $termination = LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->actingAs($this->hr);

    $this->post(route('tenant.vacations.terminations.settle', [$this->tenant, $termination]))->assertRedirect();

    expect(Balances::available($this->ana->id))->toBe(0)
        ->and($termination->refresh()->settled_by)->toBe($this->hr->id)
        ->and(AuditEntry::query()->where('event', 'leave.termination_settled')->count())->toBe(1);

    $this->post(route('tenant.vacations.terminations.settle', [$this->tenant, $termination]))->assertRedirect()->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect(LeaveEntry::query()->where('kind', EntryKind::Termination)->count())->toBe(1)
        ->and(AuditEntry::query()->where('event', 'leave.termination_settled')->count())->toBe(1);
});

it('does not settle the termination of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = LeaveTermination::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->hr)->post(route('tenant.vacations.terminations.settle', [$this->tenant, $foreign->id]))->assertNotFound();

    expect(LeaveTermination::query()->withoutGlobalScopes()->whereKey($foreign->id)->value('settled_at'))->toBeNull();
});
