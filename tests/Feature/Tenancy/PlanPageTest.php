<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PlanPage;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PlanPage::class);

beforeEach(fn () => $this->travelTo('2026-11-12 18:00 UTC'));

/** A company with an owner signed in, and a plan of `$modules`, 50 people and 2 centers. */
function planned(array $modules = ['nom035', 'attendance']): Tenant
{
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    test()->actingAs(memberOf($tenant, TenantRole::Owner));
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan' => Plan::Cumplimiento, 'seats' => 50, 'centers' => 2, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    resolve(CompanyModules::class)->syncPlan($tenant->id, $modules, CarbonImmutable::parse('2026-11-12'));
    resolve(CompanyLimits::class)->sync($tenant->id, ['seats' => 50, 'centers' => 2]);

    return $tenant;
}

it('lives at /admin/{company}/plan', function (): void {
    expect(route('tenant.plan', 'acme', absolute: false))->toBe('/admin/acme/plan');
});

it('sends guests to the login', function (): void {
    Tenant::factory()->create(['slug' => 'acme']);

    $this->get(route('tenant.plan', 'acme'))->assertRedirect(route('login'));
});

it('shows the owner the plan, the limits with what is used, and the tools', function (): void {
    $tenant = planned(['nom035', 'attendance']);
    workIn($tenant);
    $this->post(route('tenant.work-centers.store', $tenant), workCenterForm())->assertSessionHasNoErrors();

    $this->get(route('tenant.plan', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/plan')
            ->where('plan', ['name' => 'Cumplimiento', 'since' => '1 nov 2026', 'until' => '1 dic 2026'])
            ->where('usage.centers', ['used' => 1, 'limit' => 2])
            ->where('usage.people.limit', 50)
            ->where('tools', fn ($tools) => collect($tools)->firstWhere('label', 'NOM-035')['active'] === true
                && collect($tools)->firstWhere('label', 'Ley Silla')['active'] === false)
            ->has('support.email'));
});

it('names the day a tool ends when it does', function (): void {
    $tenant = planned(['nom035', 'attendance']);
    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035'], CarbonImmutable::parse('2026-12-15'));

    $this->get(route('tenant.plan', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('tools', function ($tools): bool {
            $attendance = collect($tools)->firstWhere('label', 'Asistencia');

            return $attendance['active'] === true && $attendance['endsOn'] === '15 dic 2026';
        }));
});

it('gives the support email the platform publishes, or none', function (?string $email): void {
    config(['app.support_email' => $email]);
    $tenant = planned();

    $this->get(route('tenant.plan', $tenant))->assertInertia(fn (Assert $page) => $page->where('support.email', $email));
})->with(['configured' => 'soporte@humetria.mx', 'not configured' => null]);

it('says the company has no plan, and counts what it really uses with no limit', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($tenant, TenantRole::Owner));

    $this->get(route('tenant.plan', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan', null)
            ->where('usage.centers', ['used' => 0, 'limit' => null])
            ->where('usage.people.limit', null)
            ->where('tools', fn ($tools) => collect($tools)->every(fn ($tool) => $tool['active'] === true && $tool['endsOn'] === null)));
});

it('treats an ended subscription as no plan', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($tenant, TenantRole::Owner));
    Subscription::factory()->status(App\Modules\Billing\Enums\SubscriptionStatus::Ended)->create(['tenant_id' => $tenant->id]);

    $this->get(route('tenant.plan', $tenant))->assertInertia(fn (Assert $page) => $page->where('plan', null));
});

it('carries no prices, charges or balances', function (): void {
    $tenant = planned();
    $page = $this->get(route('tenant.plan', $tenant))->assertOk()->viewData('page');
    $props = json_encode($page['props'], JSON_THROW_ON_ERROR);

    expect($props)->not->toMatch('/price|cents|charge|balance|saldo|amount|negotiated|\$/i');
});

it('is for the owner only: hr and viewer get 403', function (TenantRole $role): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($tenant, $role));

    $this->get(route('tenant.plan', $tenant))->assertForbidden();
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('answers 404 for another company, and shows each owner only their own', function (): void {
    $acme = Tenant::factory()->create(['slug' => 'acme']);
    $beta = Tenant::factory()->create(['slug' => 'beta']);
    Subscription::factory()->create(['tenant_id' => $beta->id, 'plan' => Plan::Integral]);
    $this->actingAs(memberOf($acme, TenantRole::Owner));

    $this->get(route('tenant.plan', $beta))->assertNotFound();
    $this->get(route('tenant.plan', $acme))->assertInertia(fn (Assert $page) => $page->where('plan', null));
});

it('stays open when the company is read-only: the owner still reads their plan', function (): void {
    $tenant = planned();
    $tenant->update(['restriction' => 'read_only', 'restriction_reason' => 'x', 'restricted_at' => now()]);

    $this->get(route('tenant.plan', $tenant))->assertOk();
});

it('shows a union only what applies to it: its people, with no work centers and no tools of the compliance modules', function (): void {
    $tenant = Tenant::factory()->union()->create(['slug' => 'sindicato']);
    $this->actingAs(memberOf($tenant, TenantRole::Owner));
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan' => Plan::Cumplimiento, 'seats' => 50, 'centers' => 2, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    resolve(CompanyLimits::class)->sync($tenant->id, ['seats' => 50, 'centers' => 2]);

    $this->get(route('tenant.plan', $tenant))->assertInertia(fn (Assert $page) => $page
        ->where('union', true)
        ->where('usage.centers', null)
        ->where('usage.people.limit', 50)
        ->where('tools', []));
});

it('tells a company it is not a union', function (): void {
    $tenant = planned();

    $this->get(route('tenant.plan', $tenant))->assertInertia(fn (Assert $page) => $page->where('union', false));
});
