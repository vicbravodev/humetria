<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Contracts\Receivables;
use App\Modules\Billing\Data\OverdueAccount;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\ContractedRevenue;
use App\Modules\Billing\Support\LedgerPlatformCost;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Messaging\Contracts\PlatformDeliveries;
use App\Modules\Messaging\Data\ChannelTraffic;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PlatformOverview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PlatformOverview::class, ContractedRevenue::class, LedgerPlatformCost::class);

beforeEach(fn () => $this->travelTo('2026-11-12 18:00 UTC'));

it('lives at /admin/overview', function (): void {
    expect(route('overview.index', absolute: false))->toBe('/admin/overview');
});

it('sends guests to the login', function (): void {
    $this->get(route('overview.index'))->assertRedirect(route('login'));
});

it('answers 403 to support and to anyone without a platform role', function (User $user): void {
    $this->actingAs($user)->get(route('overview.index'))->assertForbidden();
})->with([
    'support' => fn () => support(),
    'someone with no role' => fn () => User::factory()->create(),
]);

it('counts the companies by status', function (): void {
    Tenant::factory()->count(2)->create();
    Tenant::factory()->suspended()->create();

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/overview')
            ->where('companies.all', 3)
            ->where('companies.suspended', 1));
});

it('counts the demo requests by status', function (): void {
    DemoRequest::factory()->count(2)->create();
    DemoRequest::factory()->create(['status' => DemoStatus::Contacted]);

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('demos', ['all' => 3, 'new' => 2, 'contacted' => 1, 'scheduled' => 0, 'trial' => 0, 'converted' => 0, 'discarded' => 0]));
});

it('adds up the monthly income the paying subscriptions contract, before VAT', function (): void {
    $paying = Subscription::factory()->plan(Plan::Cumplimiento)->create(['seats' => 30, 'centers' => 1]);
    Subscription::factory()->plan(Plan::Cumplimiento)->status(SubscriptionStatus::Ended)->create(['seats' => 30, 'centers' => 1]);
    Subscription::factory()->plan(Plan::Cumplimiento)->status(SubscriptionStatus::Trial)->create(['seats' => 30, 'centers' => 1]);
    $one = App\Modules\Billing\Support\Quote::for($paying->plan, $paying->profile, 30, 1, $paying->catalog_version, 0, null, null)->subtotalCents;

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('revenueCents', $one));
});

it('prices each channel Integral company with the discount its own account earns', function (): void {
    $discounted = BillingAccount::factory()->consultant()->create();
    $direct = BillingAccount::factory()->consultant()->create();
    foreach ([$discounted, $discounted, $discounted, $direct, $direct] as $account) {
        planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'centers' => 3]);
    }

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('revenueCents', 3 * 792_000 + 2 * 990_000));
});

it('has zero income when nobody pays', function (): void {
    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('revenueCents', 0));
});

it('counts the companies nobody has entered for 30 days, a company that never was entered included', function (): void {
    $open = fn () => Tenant::factory()->activeUntil(now()->addMonth());
    $active = $open()->create();
    memberOf($active, TenantRole::Owner)->forceFill(['last_login_at' => now()->subDays(29)])->save();
    $stale = $open()->create();
    memberOf($stale, TenantRole::Owner)->forceFill(['last_login_at' => now()->subDays(31)])->save();
    $open()->create();
    $mixed = $open()->create();
    memberOf($mixed, TenantRole::Owner)->forceFill(['last_login_at' => now()->subDays(90)])->save();
    memberOf($mixed, TenantRole::Hr)->forceFill(['last_login_at' => now()->subDay()])->save();

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('inactive', 2));
});

it('keeps out a company entered exactly 30 days ago and counts one entered a second before', function (): void {
    $this->freezeSecond();
    $open = fn () => Tenant::factory()->activeUntil(now()->addMonth());
    memberOf($open()->create(), TenantRole::Owner)->forceFill(['last_login_at' => now()->subDays(30)])->save();
    memberOf($open()->create(), TenantRole::Owner)->forceFill(['last_login_at' => now()->subDays(30)->subSecond()])->save();

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('inactive', 1));
});

it('does not count a company that has not started: nobody could have entered it yet', function (): void {
    Tenant::factory()->create();

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('inactive', 0));
});

it('does not count an archived company as one nobody enters', function (): void {
    Tenant::factory()->create(['restriction' => 'archived', 'restriction_reason' => 'x', 'restricted_at' => now()]);

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('inactive', 0));
});

it('reads the page in the same number of queries however much the platform holds', function (): void {
    $this->actingAs(superadmin());
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('overview.index'))->assertOk();

        return count(DB::getQueryLog());
    };
    $grow = function (int $n): void {
        foreach (range(1, $n) as $ignored) {
            $tenant = Tenant::factory()->create();
            memberOf($tenant, TenantRole::Owner);
            Subscription::factory()->create(['tenant_id' => $tenant->id]);
            DemoRequest::factory()->create();
        }
    };

    $count();
    $grow(2);
    $few = $count();
    $grow(10);

    expect($count())->toBe($few);
});

it('is forbidden while impersonating', function (): void {
    expect(ForbidWhileImpersonating::guards('overview.index'))->toBeTrue();
});

it('shows each company in the console with the day its people last entered', function (): void {
    $tenant = Tenant::factory()->create();
    memberOf($tenant, TenantRole::Owner)->forceFill(['last_login_at' => '2026-11-02 18:00:00'])->save();
    memberOf($tenant, TenantRole::Hr)->forceFill(['last_login_at' => '2026-11-10 18:00:00'])->save();
    Tenant::factory()->create(['trade_name' => 'Nunca']);

    $this->actingAs(superadmin())->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenants.data', fn ($rows) => collect($rows)->firstWhere('id', $tenant->id)['last_access'] === '10 nov 2026'
                && collect($rows)->firstWhere('trade_name', 'Nunca')['last_access'] === null));
});

it('shows each user in the platform list with the day they last signed in', function (): void {
    $person = User::factory()->create(['last_login_at' => '2026-11-10 18:00:00']);
    User::factory()->create(['name' => 'Nunca Entró']);

    $this->actingAs(superadmin())->get(route('platform-users.index', ['tab' => 'people']))
        ->assertInertia(fn (Assert $page) => $page->where('users.data', fn ($rows) => collect($rows)->firstWhere('id', $person->id)['lastLogin'] === '10 nov 2026'
            && collect($rows)->firstWhere('name', 'Nunca Entró')['lastLogin'] === null));
});

it('shows the month of WhatsApp: messages, what Meta charged, what the companies were charged and the margin', function (): void {
    $tenant = Tenant::factory()->create();
    foreach (range(1, 4) as $index) {
        DB::table('usage_events')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'meter' => 'whatsapp', 'period_start' => '2026-11-01', 'quantity' => 1, 'idempotency_key' => "wamid-{$index}", 'occurred_at' => '2026-11-10 12:00:00', 'unit_cost_micros' => 170_000, 'unit_price_micros' => 221_000, 'cost_currency' => 'MXN']);
    }
    DB::table('usage_events')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'meter' => 'whatsapp', 'period_start' => '2026-10-01', 'quantity' => 1, 'idempotency_key' => 'last-month', 'occurred_at' => '2026-10-10 12:00:00', 'unit_cost_micros' => 170_000, 'unit_price_micros' => 221_000, 'cost_currency' => 'MXN']);

    $this->actingAs(superadmin())->get(route('overview.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('whatsApp', ['messages' => 4, 'costCents' => 68, 'chargedCents' => 88, 'marginCents' => 20]));
});

it('shows zeros for WhatsApp when nothing went out', function (): void {
    $this->actingAs(superadmin())->get(route('overview.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('whatsApp', ['messages' => 0, 'costCents' => 0, 'chargedCents' => 0, 'marginCents' => 0]));
});

it('lists what is past due, the account owing the longest first, from Billing', function (): void {
    $this->mock(Receivables::class)
        ->shouldReceive('overdueCents')->andReturn(1_610_000)
        ->shouldReceive('overdueAccounts')->with(3)->andReturn([
            new OverdueAccount('acc-1', 'Textiles del Norte SA de CV', 1_280_000, 38),
            new OverdueAccount('acc-2', 'Escuelas Delta AC', 330_000, 19),
        ])
        ->shouldReceive('renewingWithin')->andReturn(0);

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overdue.cents', 1_610_000)
            ->where('overdue.accounts', [
                ['id' => 'acc-1', 'name' => 'Textiles del Norte SA de CV', 'cents' => 1_280_000, 'days' => 38],
                ['id' => 'acc-2', 'name' => 'Escuelas Delta AC', 'cents' => 330_000, 'days' => 19],
            ]));
});

it('counts the demo requests waiting, and those waiting more than 48 hours', function (): void {
    DemoRequest::factory()->create(['created_at' => now()->subHours(49)]);
    DemoRequest::factory()->create(['created_at' => now()->subHours(47)]);

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demosWaiting', ['new' => 2, 'unattended' => 1]));
});

it('adds up the messages rejected today, in Mexico City, across channels', function (): void {
    $this->travelTo('2026-11-12 07:00 UTC'); // 1:00 in Mexico City: the day began at 06:00 UTC.
    $this->mock(PlatformDeliveries::class)
        ->shouldReceive('byChannel')
        ->withArgs(fn (CarbonImmutable $from, CarbonImmutable $to): bool => $from->eq(CarbonImmutable::parse('2026-11-12 06:00 UTC'))
            && $to->eq(CarbonImmutable::parse('2026-11-13 06:00 UTC')))
        ->andReturn([
            new ChannelTraffic('mail', 40, 38, 0, 2),
            new ChannelTraffic('whatsapp', 412, 388, 293, 14),
        ]);

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('rejectedToday', 16));
});

it('draws what was billed in each of the last twelve months, and the five latest entries of the audit trail', function (): void {
    $admin = superadmin();
    foreach (range(1, 7) as $n) {
        AuditEntry::query()->forceCreate(['id' => (string) Str::uuid(), 'event' => 'tenant.activated', 'metadata' => [], 'created_at' => now()->subMinutes($n)]);
    }

    $this->actingAs($admin)->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('billedByMonth', 12)
            ->where('billedByMonth.11.month', '2026-11')
            ->has('activity', 5)
            ->where('activity.0.event', 'tenant.activated'));
});
