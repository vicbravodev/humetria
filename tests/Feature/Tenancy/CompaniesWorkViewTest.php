<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Actions\AssignAccountManager;
use App\Modules\Tenancy\Actions\ExtendTrials;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AssignAccountManager::class, ExtendTrials::class);

/** @return list<string> */
function viewNames(string $status, string $q = ''): array
{
    return collect(test()->actingAs(superadmin())
        ->get(route('companies.index', ['status' => $status, 'q' => $q]))
        ->inertiaProps('tenants.data'))->pluck('trade_name')->sort()->values()->all();
}

it('tells who at Humetría answers for each company and how many of its seats it uses', function (): void {
    $manager = support();
    $manager->update(['name' => 'Laura Soporte']);
    $tenant = Tenant::factory()->create(['trade_name' => 'Con responsable', 'owner_user_id' => $manager->id]);
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'seats', 'amount' => 150, 'used' => 96]);
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'centers', 'amount' => 5, 'used' => 3]);
    Tenant::factory()->create(['trade_name' => 'Sin plan']);

    $rows = collect($this->actingAs(superadmin())->get(route('companies.index'))->inertiaProps('tenants.data'))->keyBy('trade_name');

    expect($rows['Con responsable']['account_manager'])->toBe(['id' => $manager->id, 'name' => 'Laura Soporte'])
        ->and($rows['Con responsable']['seats'])->toBe(['used' => 96, 'amount' => 150])
        ->and($rows['Sin plan']['account_manager'])->toBeNull()
        ->and($rows['Sin plan']['seats'])->toBeNull();
});

it('offers the staff, and only the staff, as the people who can answer for a company', function (): void {
    $superadmin = superadmin();
    $helper = support();
    User::factory()->create(['name' => 'Dueña de una empresa']);

    $staff = $this->actingAs($superadmin)->get(route('companies.index'))->inertiaProps('staff');

    expect(collect($staff)->pluck('id')->sort()->values()->all())->toBe(collect([$superadmin->id, $helper->id])->sort()->values()->all());
});

it('gathers the trials that end within seven days in their own view, within the search', function (): void {
    $this->travelTo('2026-10-27 18:00 UTC');
    Tenant::factory()->inTrial(now()->subDays(Tenant::TRIAL_DAYS - 7))->create(['trade_name' => 'Termina en siete']);
    Tenant::factory()->inTrial(now()->subDays(Tenant::TRIAL_DAYS - 1))->create(['trade_name' => 'Termina mañana']);
    Tenant::factory()->inTrial(now()->subDays(Tenant::TRIAL_DAYS - 8))->create(['trade_name' => 'Termina en ocho']);
    Tenant::factory()->inTrial(now()->subDays(Tenant::TRIAL_DAYS))->create(['trade_name' => 'Ya terminó']);
    Tenant::factory()->inTrial(now()->subDays(Tenant::TRIAL_DAYS - 1))->suspended()->create(['trade_name' => 'Suspendida']);

    expect(viewNames('trial_ending'))->toBe(['Termina en siete', 'Termina mañana'])
        ->and(viewNames('trial_ending', 'mañana'))->toBe(['Termina mañana']);

    $this->actingAs(superadmin())->get(route('companies.index', ['q' => 'siete']))
        ->assertInertia(fn ($page) => $page->where('counts.trial_ending', 1)->where('trialsEndingSoon', 2));
});

it('gathers the companies nobody has entered in thirty days, the ones never entered once they are thirty days old', function (): void {
    $this->travelTo('2026-10-27 18:00 UTC');
    $recent = Tenant::factory()->create(['trade_name' => 'Entró hace 29 días', 'created_at' => now()->subYear()]);
    TenantMembership::factory()->for($recent)->for(User::factory()->create(['last_login_at' => now()->subDays(29)]))->create();
    $away = Tenant::factory()->create(['trade_name' => 'Entró hace 31 días', 'created_at' => now()->subYear()]);
    TenantMembership::factory()->for($away)->for(User::factory()->create(['last_login_at' => now()->subDays(31)]))->create();
    Tenant::factory()->create(['trade_name' => 'Nunca, desde hace 31 días', 'created_at' => now()->subDays(31)]);
    Tenant::factory()->create(['trade_name' => 'Nunca, nueva', 'created_at' => now()->subDays(29)]);
    Tenant::factory()->suspended()->create(['trade_name' => 'Suspendida', 'created_at' => now()->subYear()]);

    expect(viewNames('dormant'))->toBe(['Entró hace 31 días', 'Nunca, desde hace 31 días']);
});

it('lets the superadmin say who answers for several companies at once, and take it back', function (): void {
    $manager = support();
    [$a, $b] = Tenant::factory()->count(2)->create();
    $untouched = Tenant::factory()->create();
    Log::spy();

    $this->actingAs(superadmin())
        ->post(route('companies.account-manager'), ['companies' => [$a->id, $b->id], 'user_id' => $manager->id])
        ->assertRedirect(route('companies.index'));

    expect($a->fresh()?->owner_user_id)->toBe($manager->id)
        ->and($b->fresh()?->owner_user_id)->toBe($manager->id)
        ->and($untouched->fresh()?->owner_user_id)->toBeNull();
    Log::shouldHaveReceived('info')->with('tenancy.account_manager_assigned', ['companies' => 2, 'manager_id' => $manager->id])->once();

    $this->actingAs(superadmin())->post(route('companies.account-manager'), ['companies' => [$a->id], 'user_id' => null]);

    expect($a->fresh()?->owner_user_id)->toBeNull();
});

it('never hands a company to someone outside the staff', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.account-manager'), ['companies' => [$tenant->id], 'user_id' => User::factory()->create()->id])
        ->assertSessionHasErrors('user_id');

    expect($tenant->fresh()?->owner_user_id)->toBeNull();
});

it('takes at most a page of companies and never the demo company', function (): void {
    $manager = support();
    $demo = Tenant::factory()->demo()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.account-manager'), ['companies' => Tenant::factory()->count(26)->create()->pluck('id')->all(), 'user_id' => $manager->id])
        ->assertSessionHasErrors(['companies' => 'El campo empresas no debe tener más de 25 elementos.']);

    $this->actingAs(superadmin())->post(route('companies.account-manager'), ['companies' => [$demo->id], 'user_id' => $manager->id]);

    expect($demo->fresh()?->owner_user_id)->toBeNull();
});

it('keeps the bulk actions from support and from anyone outside the staff, before reading what they sent', function (string $route, Closure $who): void {
    $this->actingAs($who())->post(route($route), [])->assertNotFound()->assertSessionHasNoErrors();
})->with([
    'account manager, support' => ['companies.account-manager', fn () => support()],
    'account manager, a person' => ['companies.account-manager', fn () => User::factory()->create()],
    'trial, support' => ['companies.trial-extension', fn () => support()],
    'trial, a person' => ['companies.trial-extension', fn () => User::factory()->create()],
]);

it('forgets who answered for a company when that person is deleted', function (): void {
    $manager = support();
    $tenant = Tenant::factory()->create(['owner_user_id' => $manager->id]);

    $manager->delete();

    expect($tenant->fresh()?->owner_user_id)->toBeNull();
});

it('extends the trials chosen: a running one from its end, an ended one from today; a paid, restricted or unstarted one stays as it is', function (): void {
    $this->travelTo('2026-10-27 18:00 UTC');
    $running = Tenant::factory()->inTrial(now()->subDays(10))->create();
    $ended = Tenant::factory()->inTrial(now()->subDays(30))->create();
    $paid = Tenant::factory()->activeUntil(now()->addMonth())->create();
    $suspended = Tenant::factory()->inTrial(now()->subDays(3))->suspended()->create();
    $unstarted = Tenant::factory()->create();
    Log::spy();

    $this->actingAs(superadmin())
        ->post(route('companies.trial-extension'), ['companies' => [$running->id, $ended->id, $paid->id, $suspended->id, $unstarted->id], 'days' => 7])
        ->assertRedirect(route('companies.index'))
        ->assertInertiaFlash('toast.message', 'Prueba extendida 7 días a 2 empresas.');

    expect($running->fresh()?->trialEndsAt()?->toIso8601String())->toBe(now()->addDays(4 + 7)->toIso8601String())
        ->and($ended->fresh()?->trialEndsAt()?->toIso8601String())->toBe(now()->addDays(7)->toIso8601String())
        ->and($paid->fresh()?->trial_started_at)->toBeNull()
        ->and($suspended->fresh()?->trial_started_at?->toIso8601String())->toBe(now()->subDays(3)->toIso8601String())
        ->and($unstarted->fresh()?->trial_started_at)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'tenant.trial_extended')->pluck('tenant_id')->sort()->values()->all())
        ->toBe(collect([$running->id, $ended->id])->sort()->values()->all());
    Log::shouldHaveReceived('info')->with('tenancy.trial_extended', ['companies' => 2, 'days' => 7])->once();
});

it('extends a trial by one to thirty days', function (int $days, bool $valid): void {
    $tenant = Tenant::factory()->inTrial(now()->subDay())->create();

    $response = $this->actingAs(superadmin())->post(route('companies.trial-extension'), ['companies' => [$tenant->id], 'days' => $days]);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('days');
})->with([
    'none' => [0, false],
    'one' => [1, true],
    'thirty' => [30, true],
    'thirty-one' => [31, false],
]);

it('carries no individual answer in its props', function (): void {
    memberOf(Tenant::factory()->create(), App\Modules\Tenancy\Enums\TenantRole::Owner);

    $props = $this->actingAs(superadmin())->get(route('companies.index'))->inertiaProps();

    expect(individualAnswersIn($props))->toBe([]);
});

it('tells each company its plan, what it pays a month and when it renews, and the page what all of them pay', function (): void {
    $this->travelTo('2026-11-25 18:00 UTC');
    $paying = Subscription::factory()->plan(Plan::Integral)->create(['renews_on' => '2026-12-15']);
    Tenant::factory()->create(['trade_name' => 'Sin plan']);

    $response = $this->actingAs(superadmin())->get(route('companies.index'));
    $rows = collect($response->inertiaProps('tenants.data'))->keyBy('id');
    $plan = $rows[$paying->tenant_id]['plan'];

    expect($plan['name'])->toBe('Integral')
        ->and($plan['renews_on'])->toBe('15 dic 2026')
        ->and($plan['monthly_cents'])->toBeGreaterThan(0)
        ->and($rows->firstWhere('trade_name', 'Sin plan')['plan'])->toBeNull()
        ->and($response->inertiaProps('revenueCents'))->toBe($plan['monthly_cents']);
});

it('narrows the companies by who answers for them, by seats almost used up and by plan, the tabs counting within', function (): void {
    $laura = support();
    $mine = Tenant::factory()->create(['trade_name' => 'De Laura', 'owner_user_id' => $laura->id]);
    $full = Tenant::factory()->create(['trade_name' => 'Casi llena']);
    TenantLimit::factory()->create(['tenant_id' => $full->id, 'resource' => 'seats', 'amount' => 100, 'used' => 80]);
    $roomy = Tenant::factory()->create(['trade_name' => 'Holgada', 'owner_user_id' => $laura->id]);
    TenantLimit::factory()->create(['tenant_id' => $roomy->id, 'resource' => 'seats', 'amount' => 100, 'used' => 79]);
    Subscription::factory()->plan(Plan::Integral)->create(['tenant_id' => $full->id]);
    Subscription::factory()->plan(Plan::Cumplimiento)->create(['tenant_id' => $mine->id]);

    $names = fn (array $query): array => collect($this->actingAs(superadmin())->get(route('companies.index', $query))->inertiaProps('tenants.data'))
        ->pluck('trade_name')->sort()->values()->all();

    expect($names(['manager' => $laura->id]))->toBe(['De Laura', 'Holgada'])
        ->and($names(['manager' => 'none']))->toBe(['Casi llena'])
        ->and($names(['usage' => 'high']))->toBe(['Casi llena'])
        ->and($names(['plan' => 'integral']))->toBe(['Casi llena'])
        ->and($names(['plan' => 'nope']))->toBe(['Casi llena', 'De Laura', 'Holgada']);

    $this->actingAs(superadmin())->get(route('companies.index', ['manager' => $laura->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.all', 2)
            ->where('filters.manager', (string) $laura->id)
            ->where('plans', [['value' => 'jornada', 'label' => 'Jornada'], ['value' => 'cumplimiento', 'label' => 'Cumplimiento'], ['value' => 'integral', 'label' => 'Integral'], ['value' => 'enterprise', 'label' => 'Enterprise'], ['value' => 'sindicato', 'label' => 'Sindicato']]));
});
