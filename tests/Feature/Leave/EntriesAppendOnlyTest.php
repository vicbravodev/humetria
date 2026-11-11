<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Organization\Models\Employee;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function insertEntry(LeaveEntitlement $period, array $attributes = []): bool
{
    return DB::table('leave_entries')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $period->tenant_id,
        'employee_id' => $period->employee_id,
        'leave_entitlement_id' => $period->id,
        'kind' => 'taken',
        'tranche' => 'statutory',
        'days' => -1,
        ...$attributes,
    ]);
}

it('rejects in the database changing an entry of the ledger', function (): void {
    $entry = LeaveEntry::factory()->create();

    expect(fn () => DB::table('leave_entries')->where('id', $entry->id)->update(['days' => 99]))->toThrow(QueryException::class, 'leave_entries is append-only');
});

it('rejects in the database deleting an entry of the ledger', function (): void {
    $entry = LeaveEntry::factory()->create();

    expect(fn () => DB::table('leave_entries')->where('id', $entry->id)->delete())->toThrow(QueryException::class, 'leave_entries is append-only');
});

it('rejects in the database emptying the ledger', function (): void {
    LeaveEntry::factory()->create();

    expect(fn () => DB::statement('TRUNCATE leave_entries'))->toThrow(QueryException::class, 'leave_entries is append-only');
});

it('stamps each entry when it is written and keeps its kind and tranche', function (): void {
    $this->travelTo('2026-03-15 18:00:00');
    $entry = LeaveEntry::factory()->create(['kind' => EntryKind::Taken, 'tranche' => Tranche::Extra, 'days' => -2])->refresh();

    expect($entry->created_at->toDateTimeString())->toBe('2026-03-15 18:00:00')
        ->and($entry->kind)->toBe(EntryKind::Taken)
        ->and($entry->tranche)->toBe(Tranche::Extra)
        ->and($entry->days)->toBe(-2);
});

it('takes in the database an entry with no period: days given in advance', function (): void {
    $person = Employee::factory()->create();

    expect(DB::table('leave_entries')->insert([
        'id' => Str::uuid()->toString(), 'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'kind' => 'taken', 'tranche' => 'statutory', 'days' => -3,
    ]))->toBeTrue();
});

it('rejects in the database an entry of zero days, of a kind or of a tranche it does not know', function (array $attributes, string $constraint): void {
    $period = LeaveEntitlement::factory()->create();

    expect(fn () => insertEntry($period, $attributes))->toThrow(QueryException::class, $constraint);
})->with([
    [['days' => 0], 'leave_entries_days_not_zero'],
    [['kind' => 'sold'], 'leave_entries_kind_known'],
    [['tranche' => 'bonus'], 'leave_entries_tranche_known'],
]);

it('rejects in the database an adjustment without who made it or without its note', function (array $attributes): void {
    $period = LeaveEntitlement::factory()->create();
    $attributes = array_map(fn (mixed $value): mixed => $value === 'user' ? User::factory()->create()->id : $value, $attributes);

    expect(fn () => insertEntry($period, ['kind' => 'adjustment', ...$attributes]))->toThrow(QueryException::class, 'leave_entries_adjustment_explained');
})->with([
    'nobody' => [['recorded_by' => null, 'note' => 'Corrección']],
    'no note' => [['recorded_by' => 'user', 'note' => null]],
    'blank note' => [['recorded_by' => 'user', 'note' => '   ']],
]);

it('rejects in the database an entry on the period of another person or of another company', function (): void {
    $period = LeaveEntitlement::factory()->create();
    $colleague = Employee::factory()->create(['tenant_id' => $period->tenant_id]);
    $stranger = Employee::factory()->create();

    expect(fn () => DB::transaction(fn () => insertEntry($period, ['employee_id' => $colleague->id])))->toThrow(QueryException::class, 'leave_entries_period_of_the_person')
        ->and(fn () => insertEntry($period, ['tenant_id' => $stranger->tenant_id, 'employee_id' => $stranger->id]))->toThrow(QueryException::class);
});

it('rejects in the database a second period for the same year of service of a person', function (): void {
    $period = LeaveEntitlement::factory()->create(['years_of_service' => 2]);
    LeaveEntitlement::factory()->create(['tenant_id' => $period->tenant_id, 'employee_id' => $period->employee_id, 'years_of_service' => 3]);
    LeaveEntitlement::factory()->create(['tenant_id' => $period->tenant_id, 'years_of_service' => 2]);

    expect(fn () => LeaveEntitlement::factory()->create(['tenant_id' => $period->tenant_id, 'employee_id' => $period->employee_id, 'years_of_service' => 2]))
        ->toThrow(QueryException::class, 'leave_entitlements_one_per_year_of_service');
});

it('rejects in the database a period that breaks its own rules', function (array $attributes, string $constraint): void {
    $period = LeaveEntitlement::factory()->create();

    expect(fn () => DB::table('leave_entitlements')->where('id', $period->id)->update($attributes))->toThrow(QueryException::class, $constraint);
})->with([
    [['years_of_service' => 0], 'leave_entitlements_years_from_the_first'],
    [['premium_percent' => 24], 'leave_entitlements_premium_floor'],
    [['statutory_days' => -1], 'leave_entitlements_days_not_negative'],
    [['extra_days' => -1], 'leave_entitlements_days_not_negative'],
    [['source' => 'gift'], 'leave_entitlements_source_known'],
]);

it('rejects in the database a period of a person of another company', function (): void {
    $stranger = Employee::factory()->create();

    expect(fn () => LeaveEntitlement::factory()->create(['employee_id' => $stranger->id]))->toThrow(QueryException::class, 'leave_entitlements_employee_id_tenant_id_foreign');
});
