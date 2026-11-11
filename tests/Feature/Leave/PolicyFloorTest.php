<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Leave\Support\StatutoryDays;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function insertPolicyRow(LeavePolicy $policy, array $attributes): bool
{
    return DB::table('leave_policy_rows')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $policy->tenant_id,
        'leave_policy_id' => $policy->id,
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]);
}

it('keeps the floor of the database equal to the table of art. 76', function (): void {
    $rows = DB::select('select years, leave_statutory_days(years) as days from generate_series(1, 40) as years');

    expect($rows)->toHaveCount(40);

    foreach ($rows as $row) {
        expect($row->days)->toBe(StatutoryDays::current($row->years), "year {$row->years}");
    }
});

it('rejects in the database a tier that gives fewer days than the law', function (): void {
    $policy = LeavePolicy::factory()->create();
    $tier = ['kind' => 'tier', 'year_from' => 1, 'year_to' => 5];

    // The fifth year asks for 20.
    expect(insertPolicyRow($policy, [...$tier, 'days' => 20]))->toBeTrue()
        ->and(fn () => insertPolicyRow($policy, [...$tier, 'days' => 19]))->toThrow(QueryException::class, 'leave_policy_rows_shape');
});

it('rejects in the database a tier whose years are not a range from the first on', function (array $years): void {
    $policy = LeavePolicy::factory()->create();

    expect(fn () => insertPolicyRow($policy, ['kind' => 'tier', 'days' => 40, ...$years]))->toThrow(QueryException::class, 'leave_policy_rows_shape');
})->with([
    'from year zero' => [['year_from' => 0, 'year_to' => 5]],
    'backwards' => [['year_from' => 6, 'year_to' => 5]],
    'without years' => [['year_from' => null, 'year_to' => null]],
]);

it('rejects in the database a fixed day without a label or without days', function (array $row): void {
    $policy = LeavePolicy::factory()->create();

    expect(fn () => insertPolicyRow($policy, ['kind' => 'fixed', ...$row]))->toThrow(QueryException::class, 'leave_policy_rows_shape');
})->with([
    'no label' => [['label' => null, 'days' => 1]],
    'blank label' => [['label' => '  ', 'days' => 1]],
    'no days' => [['label' => 'Cumpleaños', 'days' => 0]],
    'with years' => [['label' => 'Cumpleaños', 'days' => 1, 'year_from' => 1, 'year_to' => 5]],
]);

it('takes in the database a fixed day with its label', function (): void {
    expect(insertPolicyRow(LeavePolicy::factory()->create(), ['kind' => 'fixed', 'label' => 'Cumpleaños', 'days' => 1]))->toBeTrue();
});

it('rejects in the database a kind of row it does not know', function (): void {
    expect(fn () => insertPolicyRow(LeavePolicy::factory()->create(), ['kind' => 'bonus', 'label' => 'Bono', 'days' => 1]))->toThrow(QueryException::class);
});

it('rejects in the database a premium under 25 percent', function (): void {
    expect(LeavePolicy::factory()->create(['premium_percent' => 25])->premium_percent)->toBe(25)
        ->and(fn () => LeavePolicy::factory()->create(['premium_percent' => 24]))->toThrow(QueryException::class, 'leave_policies_premium_floor');
});

it('rejects in the database a work week it does not know, an expiry of zero months and a cap below zero', function (array $attributes): void {
    $policy = LeavePolicy::factory()->create();

    expect(fn () => DB::table('leave_policies')->where('id', $policy->id)->update($attributes))->toThrow(QueryException::class);
})->with([
    [['work_week' => 'sun_sat']],
    [['extra_expires_months' => 0]],
    [['extra_carryover_cap_days' => -1]],
]);

it('rejects in the database two policies of a company with the same name, and a row of another company', function (): void {
    $policy = LeavePolicy::factory()->create(['name' => 'Corporativo']);
    $other = LeavePolicy::factory()->create(['name' => 'Corporativo']);

    // Each in its own savepoint: a statement Postgres rejects aborts the transaction it runs in.
    expect(fn () => DB::transaction(fn () => LeavePolicy::factory()->create(['tenant_id' => $policy->tenant_id, 'name' => 'Corporativo'])))->toThrow(QueryException::class, 'leave_policies_tenant_id_name_unique')
        ->and(fn () => DB::table('leave_policy_rows')->insert([
            'id' => Str::uuid()->toString(), 'tenant_id' => $other->tenant_id, 'leave_policy_id' => $policy->id, 'kind' => 'fixed', 'label' => 'Cumpleaños', 'days' => 1,
        ]))->toThrow(QueryException::class, 'leave_policy_rows_leave_policy_id_tenant_id_foreign');
});

it('does not read the policies of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $ofB = LeavePolicy::factory()->create(['tenant_id' => $b->id]);
    $rowOfB = LeavePolicyRow::factory()->create(['tenant_id' => $b->id, 'leave_policy_id' => $ofB->id]);

    workIn($a);
    expect(LeavePolicy::query()->count())->toBe(0)
        ->and(LeavePolicy::query()->find($ofB->id))->toBeNull()
        ->and(LeavePolicyRow::query()->find($rowOfB->id))->toBeNull()
        ->and(LeavePolicy::query()->whereKey($ofB->id)->update(['name' => 'Tomada']))->toBe(0);

    workIn($b);
    expect(LeavePolicy::query()->count())->toBe(1)
        ->and(LeavePolicyRow::query()->count())->toBe(1);
});
