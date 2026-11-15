<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\User;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PlatformOverview;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PlatformOverview::class);

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
        ->assertInertia(fn (Assert $page) => $page->where('demos', ['all' => 3, 'new' => 2, 'contacted' => 1, 'converted' => 0, 'discarded' => 0]));
});

it('adds up the monthly income the paying subscriptions contract, before VAT', function (): void {
    $paying = Subscription::factory()->plan(Plan::Cumplimiento)->create(['seats' => 30, 'centers' => 1]);
    Subscription::factory()->plan(Plan::Cumplimiento)->status(SubscriptionStatus::Ended)->create(['seats' => 30, 'centers' => 1]);
    Subscription::factory()->plan(Plan::Cumplimiento)->status(SubscriptionStatus::Trial)->create(['seats' => 30, 'centers' => 1]);
    $one = App\Modules\Billing\Support\Quote::for($paying->plan, $paying->profile, 30, 1, $paying->catalog_version, 0, null, null)->subtotalCents;

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('revenueCents', $one));
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

    $this->actingAs(superadmin())->get(route('platform-users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('users.data', fn ($rows) => collect($rows)->firstWhere('id', $person->id)['lastLogin'] === '10 nov 2026'
            && collect($rows)->firstWhere('name', 'Nunca Entró')['lastLogin'] === null));
});
