<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\GrantEntitlement;
use App\Modules\Leave\Actions\GrantEntitlements;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\PolicyTerms;
use App\Modules\Organization\Contracts\Seniority;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;

mutates(GrantEntitlement::class, GrantEntitlements::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2026-01-01']);
    // 18:00 UTC is noon in Mexico City.
    $this->travelTo('2026-03-15 18:00:00');
});

function hired(Tenant $tenant, string $on, array $attributes = []): Employee
{
    return Employee::factory()->create(['tenant_id' => $tenant->id, 'hired_on' => $on, ...$attributes]);
}

/**
 * @return list<array<string, mixed>>
 */
function periodsOf(Employee $person): array
{
    return array_values(LeaveEntitlement::query()->withoutGlobalScopes()->where('employee_id', $person->id)->orderBy('years_of_service')->get()
        ->map(fn (LeaveEntitlement $period): array => ['years' => $period->years_of_service, 'anniversary_on' => $period->anniversary_on->toDateString(), 'statutory_days' => $period->statutory_days])->all());
}

it('grants the period of the year of service a person completes today, with its window and its days in the ledger', function (): void {
    $ana = hired($this->tenant, '2024-03-15');

    expect(resolve(GrantEntitlements::class)->handle())->toBe(1);

    $period = LeaveEntitlement::query()->withoutGlobalScopes()->sole();
    expect($period->only(['tenant_id', 'employee_id', 'years_of_service', 'leave_policy_id', 'policy_name', 'premium_percent', 'statutory_days', 'extra_days', 'extra_expires_on', 'source']))->toBe([
        'tenant_id' => $this->tenant->id, 'employee_id' => $ana->id, 'years_of_service' => 2, 'leave_policy_id' => null, 'policy_name' => 'Ley', 'premium_percent' => 25,
        'statutory_days' => 14, 'extra_days' => 0, 'extra_expires_on' => null, 'source' => 'anniversary',
    ])->and([$period->anniversary_on->toDateString(), $period->granted_on->toDateString(), $period->enjoy_by->toDateString(), $period->prescribes_on->toDateString()])
        ->toBe(['2026-03-15', '2026-03-15', '2026-09-15', '2027-09-15'])
        ->and(LeaveEntry::query()->withoutGlobalScopes()->get()->map->only(['tenant_id', 'employee_id', 'leave_entitlement_id', 'kind', 'tranche', 'days'])->all())->toBe([
            ['tenant_id' => $this->tenant->id, 'employee_id' => $ana->id, 'leave_entitlement_id' => $period->id, 'kind' => EntryKind::Granted, 'tranche' => Tranche::Statutory, 'days' => 14],
        ]);
});

it('grants nothing the day before the anniversary', function (): void {
    hired($this->tenant, '2024-03-16');

    expect(resolve(GrantEntitlements::class)->handle())->toBe(0)
        ->and(LeaveEntitlement::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('decides the day in Mexico, not in UTC', function (): void {
    // 05:30 UTC of the 15th is still the 14th in Mexico City; half an hour later it is the 15th.
    $ana = hired($this->tenant, '2024-03-15');

    $this->travelTo('2026-03-15 05:30:00');
    expect(resolve(GrantEntitlements::class)->handle())->toBe(0);

    $this->travelTo('2026-03-15 06:30:00');
    expect(resolve(GrantEntitlements::class)->handle())->toBe(1)
        ->and(periodsOf($ana))->toBe([['years' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]]);
});

it('grants a period once, however many times it runs', function (): void {
    $ana = hired($this->tenant, '2024-03-15');

    expect(resolve(GrantEntitlements::class)->handle())->toBe(1)
        ->and(resolve(GrantEntitlements::class)->handle())->toBe(0)
        ->and(periodsOf($ana))->toHaveCount(1)
        ->and(LeaveEntry::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('makes up the anniversaries of the days it did not run, with their own date', function (): void {
    $ana = hired($this->tenant, '2024-03-15');
    $this->travelTo('2026-03-18 18:00:00');

    resolve(GrantEntitlements::class)->handle();

    $period = LeaveEntitlement::query()->withoutGlobalScopes()->sole();
    expect([$period->employee_id, $period->anniversary_on->toDateString(), $period->granted_on->toDateString(), $period->enjoy_by->toDateString()])
        ->toBe([$ana->id, '2026-03-15', '2026-03-18', '2026-09-15']);
});

it('grants only the anniversaries since the company started, not the earlier ones', function (): void {
    $veteran = hired($this->tenant, '2006-03-15');

    expect(resolve(GrantEntitlements::class)->handle())->toBe(1)
        ->and(periodsOf($veteran))->toBe([['years' => 20, 'anniversary_on' => '2026-03-15', 'statutory_days' => 26]]);
});

it('counts the day the company started as the first one it grants', function (string $trackingSince, int $granted): void {
    LeaveSetting::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['tracking_since' => $trackingSince]);
    hired($this->tenant, '2024-03-15');

    expect(resolve(GrantEntitlements::class)->handle())->toBe($granted);
})->with([
    'started on the anniversary' => ['2026-03-15', 1],
    'started the day after' => ['2026-03-16', 0],
]);

it('grants every anniversary it missed since the start, each with the days of its year', function (): void {
    LeaveSetting::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['tracking_since' => '2024-01-01']);
    $ana = hired($this->tenant, '2022-03-15');

    expect(resolve(GrantEntitlements::class)->handle())->toBe(3)
        ->and(periodsOf($ana))->toBe([
            ['years' => 2, 'anniversary_on' => '2024-03-15', 'statutory_days' => 14],
            ['years' => 3, 'anniversary_on' => '2025-03-15', 'statutory_days' => 16],
            ['years' => 4, 'anniversary_on' => '2026-03-15', 'statutory_days' => 18],
        ]);
});

it('grants the first year of who was hired on a 29 February on the 28th', function (): void {
    LeaveSetting::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['tracking_since' => '2025-01-01']);
    $ana = hired($this->tenant, '2024-02-29');

    $this->travelTo('2025-02-27 18:00:00');
    expect(resolve(GrantEntitlements::class)->handle())->toBe(0);

    $this->travelTo('2025-02-28 18:00:00');
    expect(resolve(GrantEntitlements::class)->handle())->toBe(1)
        ->and(periodsOf($ana))->toBe([['years' => 1, 'anniversary_on' => '2025-02-28', 'statutory_days' => 12]]);
});

it('does not grant a second period of the same year when the hire date is corrected afterwards', function (): void {
    $ana = hired($this->tenant, '2024-03-15');
    resolve(GrantEntitlements::class)->handle();

    $ana->update(['hired_on' => '2024-03-10']);
    $this->travelTo('2026-03-16 18:00:00');

    expect(resolve(GrantEntitlements::class)->handle())->toBe(0)
        ->and(periodsOf($ana))->toBe([['years' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]]);
});

it('grants nothing to who has no start date, and lists them as missing it', function (): void {
    $dated = hired($this->tenant, '2024-03-15');
    $undated = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'hired_on' => null]);
    $gone = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'hired_on' => null]);
    Log::spy();

    expect(resolve(GrantEntitlements::class)->handle())->toBe(1)
        ->and(periodsOf($undated))->toBe([])
        ->and(periodsOf($dated))->toHaveCount(1);
    Log::shouldHaveReceived('info')->with('leave.start_date_missing', ['people' => 1])->once();

    workIn($this->tenant);
    expect(resolve(GrantEntitlements::class)->missingStartDate())->toBe([$undated->id])
        ->and($gone->deactivated_at)->not->toBeNull();
});

it('does not say a start date is missing when nobody lacks one', function (): void {
    hired($this->tenant, '2024-03-15');
    Log::spy();

    resolve(GrantEntitlements::class)->handle();

    Log::shouldNotHaveReceived('info', ['leave.start_date_missing', Mockery::any()]);
    workIn($this->tenant);
    expect(resolve(GrantEntitlements::class)->missingStartDate())->toBe([]);
});

it('counts the seniority the company recognizes before the hire date', function (): void {
    $ana = hired($this->tenant, '2025-06-01', ['seniority_on' => '2023-03-15']);

    resolve(GrantEntitlements::class)->handle();

    expect(periodsOf($ana))->toBe([['years' => 3, 'anniversary_on' => '2026-03-15', 'statutory_days' => 16]]);
});

it('grants nothing to who no longer works there', function (): void {
    $gone = hired($this->tenant, '2024-03-15', ['deactivated_at' => now()]);

    expect(resolve(GrantEntitlements::class)->handle())->toBe(0)
        ->and(periodsOf($gone))->toBe([]);
});

it('grants with the policy of the person: its name, its premium, its extra days and their expiry', function (): void {
    $policy = LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Corporativo', 'premium_percent' => 40, 'extra_expires_months' => 12]);
    LeavePolicyRow::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id, 'year_from' => 1, 'year_to' => 5, 'days' => 20]);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id, 'scope' => PolicyScope::Default]);
    $ana = hired($this->tenant, '2024-03-15');

    resolve(GrantEntitlements::class)->handle();

    $period = LeaveEntitlement::query()->withoutGlobalScopes()->sole();
    expect($period->only(['employee_id', 'leave_policy_id', 'policy_name', 'premium_percent', 'statutory_days', 'extra_days']))->toBe([
        'employee_id' => $ana->id, 'leave_policy_id' => $policy->id, 'policy_name' => 'Corporativo', 'premium_percent' => 40, 'statutory_days' => 14, 'extra_days' => 6,
    ])->and($period->extra_expires_on?->toDateString())->toBe('2027-03-15')
        ->and(LeaveEntry::query()->withoutGlobalScopes()->orderByDesc('days')->get()->map(fn (LeaveEntry $entry): array => [$entry->kind, $entry->tranche, $entry->days, $entry->leave_entitlement_id])->all())->toBe([
            [EntryKind::Granted, Tranche::Statutory, 14, $period->id],
            [EntryKind::Granted, Tranche::Extra, 6, $period->id],
        ]);
});

it('ends the expiry of the extra days on the last day of a month too short', function (): void {
    workIn($this->tenant);
    $ana = hired($this->tenant, '2024-08-31');
    $terms = new PolicyTerms(null, 'Ley', 25, WorkWeek::MonFri, false, 6, null, [], 1);

    $period = resolve(GrantEntitlement::class)->handle($ana->id, 2, CarbonImmutable::parse('2026-08-31'), $terms);

    expect($period?->extra_expires_on?->toDateString())->toBe('2027-02-28')
        ->and($period?->enjoy_by->toDateString())->toBe('2027-02-28');
});

it('answers nothing, and writes nothing, for a year of service that already has its period', function (): void {
    workIn($this->tenant);
    $ana = hired($this->tenant, '2024-03-15');
    $terms = PolicyTerms::statutory(WorkWeek::MonFri);
    $first = resolve(GrantEntitlement::class)->handle($ana->id, 2, CarbonImmutable::parse('2026-03-15'), $terms);
    Log::spy();

    expect($first)->toBeInstanceOf(LeaveEntitlement::class)
        ->and(resolve(GrantEntitlement::class)->handle($ana->id, 2, CarbonImmutable::parse('2026-03-10'), $terms))->toBeNull()
        ->and(LeaveEntitlement::query()->count())->toBe(1)
        ->and(LeaveEntry::query()->count())->toBe(1);
    Log::shouldNotHaveReceived('info');
});

it('does not take the period of another person or of another year for the one asked', function (): void {
    workIn($this->tenant);
    $ana = hired($this->tenant, '2024-03-15');
    $beto = hired($this->tenant, '2024-03-15');
    $terms = PolicyTerms::statutory(WorkWeek::MonFri);
    resolve(GrantEntitlement::class)->handle($ana->id, 1, CarbonImmutable::parse('2025-03-15'), $terms);
    resolve(GrantEntitlement::class)->handle($beto->id, 2, CarbonImmutable::parse('2026-03-15'), $terms);

    expect(resolve(GrantEntitlement::class)->handle($ana->id, 2, CarbonImmutable::parse('2026-03-15'), $terms))->toBeInstanceOf(LeaveEntitlement::class);
});

it('grants nothing in a company that does not use vacations', function (): void {
    $other = Tenant::factory()->create();
    $outside = hired($other, '2024-03-15');
    $inside = hired($this->tenant, '2024-03-15');

    expect(resolve(GrantEntitlements::class)->handle())->toBe(1)
        ->and(periodsOf($outside))->toBe([])
        ->and(periodsOf($inside))->toHaveCount(1);
});

it('grants nothing in a company that may not be used', function (): void {
    $blocked = Tenant::factory()->deactivated()->create();
    LeaveSetting::factory()->create(['tenant_id' => $blocked->id]);
    $person = hired($blocked, '2024-03-15');

    expect(resolve(GrantEntitlements::class)->handle())->toBe(0)
        ->and(periodsOf($person))->toBe([]);
});

it('grants in each company with its own policy and its own start', function (): void {
    $other = Tenant::factory()->create();
    LeaveSetting::factory()->create(['tenant_id' => $other->id, 'tracking_since' => '2025-01-01']);
    $policy = LeavePolicy::factory()->create(['tenant_id' => $other->id, 'name' => 'De la otra']);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $other->id, 'leave_policy_id' => $policy->id]);
    $here = hired($this->tenant, '2023-03-15');
    $there = hired($other, '2023-03-15');

    expect(resolve(GrantEntitlements::class)->handle())->toBe(3)
        ->and(periodsOf($here))->toHaveCount(1)
        ->and(periodsOf($there))->toHaveCount(2)
        ->and(LeaveEntitlement::query()->withoutGlobalScopes()->where('employee_id', $here->id)->sole()->policy_name)->toBe('Ley')
        ->and(LeaveEntitlement::query()->withoutGlobalScopes()->where('employee_id', $there->id)->first()?->policy_name)->toBe('De la otra');
});

it('keeps granting in the other companies when one fails', function (): void {
    Exceptions::fake();
    $failing = Tenant::factory()->create();
    LeaveSetting::factory()->create(['tenant_id' => $failing->id]);
    hired($failing, '2024-03-15');
    $ana = hired($this->tenant, '2024-03-15');
    $real = resolve(Seniority::class);
    $this->app->instance(Seniority::class, new readonly class($real, $failing->id) implements Seniority
    {
        public function __construct(private Seniority $real, private string $failingId) {}

        public function startDatesFor(array $employeeIds): array
        {
            return resolve(CurrentTenant::class)->id() === $this->failingId ? throw new RuntimeException('Broken company.') : $this->real->startDatesFor($employeeIds);
        }
    });

    expect(resolve(GrantEntitlements::class)->handle())->toBe(1)
        ->and(periodsOf($ana))->toHaveCount(1);
    Exceptions::assertReported(fn (RuntimeException $failure): bool => $failure->getMessage() === 'Broken company.');
});

it('logs each period with ids and counts, and the total of the run', function (): void {
    $ana = hired($this->tenant, '2024-03-15');
    Log::spy();

    resolve(GrantEntitlements::class)->handle();

    $period = LeaveEntitlement::query()->withoutGlobalScopes()->sole();
    Log::shouldHaveReceived('info')->with('leave.entitlement_granted', ['entitlement_id' => $period->id, 'employee_id' => $ana->id, 'years_of_service' => 2, 'statutory_days' => 14, 'extra_days' => 0])->once();
    Log::shouldHaveReceived('info')->with('leave.entitlements_granted', ['granted' => 1])->once();
});

it('runs as a command and reports what it granted', function (): void {
    hired($this->tenant, '2024-03-15');

    $this->artisan('leave:grant-entitlements')->expectsOutput('Periodos otorgados: 1')->assertSuccessful();
});
