<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Leave\Actions\SavePolicy;
use App\Modules\Leave\Enums\RowKind;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(SavePolicy::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function policyData(array $overrides = []): array
{
    return [
        'name' => 'Corporativo',
        'premium_percent' => 30,
        'work_week' => 'mon_sat',
        'allows_advance' => true,
        'extra_expires_months' => 12,
        'extra_carryover_cap_days' => 5,
        'rows' => [
            ['kind' => 'tier', 'year_from' => 1, 'year_to' => 5, 'days' => 20],
            ['kind' => 'fixed', 'label' => 'Cumpleaños', 'days' => 1],
        ],
        ...$overrides,
    ];
}

/**
 * @return array<string, list<string>>
 */
function policyErrors(Closure $save): array
{
    try {
        $save();
    } catch (ValidationException $refused) {
        return $refused->errors();
    }

    return [];
}

it('saves a policy with its rows', function (): void {
    $policy = resolve(SavePolicy::class)->handle(policyData());

    expect($policy->refresh()->only(['tenant_id', 'name', 'premium_percent', 'work_week', 'allows_advance', 'extra_expires_months', 'extra_carryover_cap_days', 'archived_at']))->toBe([
        'tenant_id' => $this->tenant->id, 'name' => 'Corporativo', 'premium_percent' => 30, 'work_week' => WorkWeek::MonSat, 'allows_advance' => true,
        'extra_expires_months' => 12, 'extra_carryover_cap_days' => 5, 'archived_at' => null,
    ])->and($policy->rows()->orderBy('kind', 'desc')->get()->map->only(['tenant_id', 'kind', 'label', 'year_from', 'year_to', 'days'])->all())->toBe([
        ['tenant_id' => $this->tenant->id, 'kind' => RowKind::Tier, 'label' => null, 'year_from' => 1, 'year_to' => 5, 'days' => 20],
        ['tenant_id' => $this->tenant->id, 'kind' => RowKind::Fixed, 'label' => 'Cumpleaños', 'year_from' => null, 'year_to' => null, 'days' => 1],
    ]);
});

it('saves a policy with no rows, no expiry and no cap: the days of the law with a better premium', function (): void {
    $policy = resolve(SavePolicy::class)->handle(policyData(['rows' => [], 'extra_expires_months' => null, 'extra_carryover_cap_days' => null, 'allows_advance' => false, 'premium_percent' => 25]));

    expect($policy->refresh()->premium_percent)->toBe(25)
        ->and($policy->allows_advance)->toBeFalse()
        ->and($policy->extra_expires_months)->toBeNull()
        ->and($policy->extra_carryover_cap_days)->toBeNull()
        ->and($policy->rows()->count())->toBe(0);
});

it('refuses a tier under the law, naming the days the law gives', function (): void {
    $errors = policyErrors(fn () => resolve(SavePolicy::class)->handle(policyData(['rows' => [['kind' => 'tier', 'year_from' => 6, 'year_to' => 10, 'days' => 21]]])));

    expect($errors)->toBe(['rows.0.days' => ['No puede ser menor a los 22 días que marca la ley.']])
        ->and(LeavePolicy::query()->count())->toBe(0);
});

it('measures a tier against its last year, the one that asks for the most', function (): void {
    // Years 5 to 6: the fifth asks for 20 and the sixth for 22.
    $rows = fn (int $days): array => ['rows' => [['kind' => 'tier', 'year_from' => 5, 'year_to' => 6, 'days' => $days]]];

    expect(policyErrors(fn () => resolve(SavePolicy::class)->handle(policyData($rows(21)))))->toBe(['rows.0.days' => ['No puede ser menor a los 22 días que marca la ley.']])
        ->and(resolve(SavePolicy::class)->handle(policyData($rows(22)))->rows()->count())->toBe(1);
});

it('refuses tiers that overlap, and takes the ones that only touch', function (): void {
    $rows = fn (int $from): array => ['rows' => [
        ['kind' => 'tier', 'year_from' => 1, 'year_to' => 5, 'days' => 20],
        ['kind' => 'tier', 'year_from' => $from, 'year_to' => 8, 'days' => 30],
    ]];

    expect(policyErrors(fn () => resolve(SavePolicy::class)->handle(policyData($rows(5)))))->toBe(['rows.1.year_from' => ['Se traslapa con otro renglón.']])
        ->and(LeavePolicy::query()->count())->toBe(0)
        ->and(resolve(SavePolicy::class)->handle(policyData($rows(6)))->rows()->count())->toBe(2);
});

it('finds the overlap whatever the order of the tiers', function (): void {
    $errors = policyErrors(fn () => resolve(SavePolicy::class)->handle(policyData(['rows' => [
        ['kind' => 'tier', 'year_from' => 6, 'year_to' => 10, 'days' => 30],
        ['kind' => 'fixed', 'label' => 'Cumpleaños', 'days' => 1],
        ['kind' => 'tier', 'year_from' => 1, 'year_to' => 6, 'days' => 30],
    ]])));

    expect($errors)->toBe(['rows.2.year_from' => ['Se traslapa con otro renglón.']]);
});

it('refuses a tier whose years are not a range from the first on', function (int $from, int $to): void {
    $errors = policyErrors(fn () => resolve(SavePolicy::class)->handle(policyData(['rows' => [['kind' => 'tier', 'year_from' => $from, 'year_to' => $to, 'days' => 40]]])));

    expect($errors)->toBe(['rows.0.year_from' => ['Los años van del 1 en adelante, de menor a mayor.']]);
})->with([[0, 5], [6, 5]]);

it('takes a tier of a single year', function (): void {
    expect(resolve(SavePolicy::class)->handle(policyData(['rows' => [['kind' => 'tier', 'year_from' => 1, 'year_to' => 1, 'days' => 12]]]))->rows()->count())->toBe(1);
});

it('refuses a fixed day without a name or without days', function (array $row, array $expected): void {
    expect(policyErrors(fn () => resolve(SavePolicy::class)->handle(policyData(['rows' => [['kind' => 'fixed', ...$row]]]))))->toBe($expected);
})->with([
    'blank label' => [['label' => '  ', 'days' => 1], ['rows.0.label' => ['Ponle un nombre a estos días.']]],
    'no label' => [['days' => 1], ['rows.0.label' => ['Ponle un nombre a estos días.']]],
    'no days' => [['label' => 'Cumpleaños', 'days' => 0], ['rows.0.days' => ['Debe ser al menos 1 día.']]],
]);

it('refuses a premium under 25 percent and takes 25', function (): void {
    expect(policyErrors(fn () => resolve(SavePolicy::class)->handle(policyData(['premium_percent' => 24]))))->toBe(['premium_percent' => ['La prima no puede ser menor a 25 %.']])
        ->and(resolve(SavePolicy::class)->handle(policyData(['premium_percent' => 25]))->premium_percent)->toBe(25);
});

it('refuses a name another policy of the company has, and takes the one of another company', function (): void {
    LeavePolicy::factory()->create(['name' => 'Corporativo']);
    resolve(SavePolicy::class)->handle(policyData());

    expect(policyErrors(fn () => resolve(SavePolicy::class)->handle(policyData())))->toBe(['name' => ['Ya hay una política con ese nombre.']]);
});

it('changes a policy and replaces its rows', function (): void {
    $policy = resolve(SavePolicy::class)->handle(policyData());

    $saved = resolve(SavePolicy::class)->handle(policyData(['premium_percent' => 50, 'rows' => [['kind' => 'tier', 'year_from' => 1, 'year_to' => 10, 'days' => 25]]]), $policy->id);

    expect($saved->id)->toBe($policy->id)
        ->and($saved->refresh()->premium_percent)->toBe(50)
        ->and(LeavePolicy::query()->count())->toBe(1)
        ->and(LeavePolicyRow::query()->get()->map->only(['year_from', 'year_to', 'days'])->all())->toBe([['year_from' => 1, 'year_to' => 10, 'days' => 25]]);
});

it('does not change the policy of another company', function (): void {
    $ofOther = LeavePolicy::factory()->create();

    expect(fn () => resolve(SavePolicy::class)->handle(policyData(), $ofOther->id))->toThrow(ModelNotFoundException::class)
        ->and($ofOther->refresh()->name)->not->toBe('Corporativo');
});

it('logs and audits the save with ids and counts only', function (): void {
    Log::spy();

    $policy = resolve(SavePolicy::class)->handle(policyData());

    Log::shouldHaveReceived('info')->once()->with('leave.policy_saved', ['policy_id' => $policy->id, 'rows' => 2]);
    $entry = AuditEntry::query()->where('event', 'leave.policy_saved')->sole();
    expect($entry->tenant_id)->toBe($this->tenant->id)
        ->and($entry->subject_id)->toBe($policy->id)
        ->and($entry->metadata)->toBe(['rows' => 2, 'premium_percent' => 30]);
});

it('writes nothing when the policy is refused', function (): void {
    Log::spy();

    policyErrors(fn () => resolve(SavePolicy::class)->handle(policyData(['premium_percent' => 10])));

    Log::shouldNotHaveReceived('info');
    expect(AuditEntry::query()->where('event', 'leave.policy_saved')->count())->toBe(0);
});
