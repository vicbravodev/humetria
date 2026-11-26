<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

it('lives under /admin/billing/plans and sends guests to the login', function (): void {
    expect(route('billing.plans.index', absolute: false))->toBe('/admin/billing/plans');

    $this->get(route('billing.plans.index'))->assertRedirect(route('login'));
});

it('answers 404 to support and to everyone who is not the superadmin', function (Closure $who): void {
    $this->actingAs($who())->get(route('billing.plans.index'))->assertNotFound();
})->with([
    'support' => [fn (): User => support()],
    'a user' => [fn (): User => User::factory()->create()],
]);

it('shows the catalog in force, its version and each plan with its price, centers and modules, read from the code', function (): void {
    $this->actingAs(superadmin())->get(route('billing.plans.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('billing/plans')
        ->where('catalog.version', 'v2')
        ->where('catalog.effective_on', '26 nov 2026')
        ->where('catalog.vat_percent', 16)
        ->where('catalog.modules', ['attendance', 'vacations', 'org_chart', 'nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate', 'compensation', 'sso'])
        ->has('catalog.plans', 4)
        ->where('catalog.plans.0', ['key' => 'jornada', 'label' => 'Jornada', 'rate' => '$39', 'flat' => null, 'minimum' => '$1,990', 'included_centers' => 1, 'modules' => ['attendance', 'vacations', 'org_chart'], 'companies' => 0, 'renewing' => 0])
        ->where('catalog.plans.3.key', 'sindicato')
        ->where('catalog.plans.3.flat', '$1,990')
        ->where('catalog.plans.3.rate', null)
        ->where('catalog.plans.3.minimum', null));
});

it('counts the companies on each plan, and of them the ones that renew in the next 90 days', function (): void {
    Subscription::factory()->plan(Plan::Integral)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    Subscription::factory()->plan(Plan::Integral)->status(SubscriptionStatus::PastDue)->create(['starts_on' => '2026-11-01', 'renews_on' => '2027-02-24']);
    Subscription::factory()->plan(Plan::Integral)->create(['starts_on' => '2026-11-01', 'renews_on' => '2027-02-25']);
    Subscription::factory()->plan(Plan::Integral)->status(SubscriptionStatus::CancelScheduled)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    Subscription::factory()->plan(Plan::Integral)->status(SubscriptionStatus::Trial)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    Subscription::factory()->plan(Plan::Integral)->status(SubscriptionStatus::Ended)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);

    $this->actingAs(superadmin())->get(route('billing.plans.index'))->assertInertia(fn (Assert $page) => $page
        ->where('catalog.plans.2.key', 'integral')
        ->where('catalog.plans.2.companies', 4)
        ->where('catalog.plans.2.renewing', 2));
});

it('lists the negotiated prices in force, the one that ends first on top, by company, a page at a time', function (): void {
    $acme = Tenant::factory()->create(['trade_name' => 'Acme Industrial']);
    $zeta = Tenant::factory()->create(['trade_name' => 'Zeta Servicios']);
    $open = Tenant::factory()->create(['trade_name' => 'Abierta']);
    Subscription::factory()->plan(Plan::Integral)->create(['tenant_id' => $zeta->id, 'negotiated_price_cents' => 1_500_000, 'negotiated_valid_until' => '2027-03-01']);
    Subscription::factory()->create(['tenant_id' => $acme->id, 'negotiated_price_cents' => 250_000, 'negotiated_valid_until' => '2026-12-31']);
    Subscription::factory()->create(['tenant_id' => $open->id, 'negotiated_price_cents' => 300_000]);
    Subscription::factory()->create(['negotiated_price_cents' => 100_000, 'negotiated_valid_until' => '2026-11-25']); // expired
    Subscription::factory()->status(SubscriptionStatus::Ended)->create(['negotiated_price_cents' => 100_000]);
    Subscription::factory()->create(); // the table price

    $this->actingAs(superadmin())->get(route('billing.plans.index'))->assertInertia(fn (Assert $page) => $page
        ->where('negotiated.total', 3)
        ->where('negotiated.data.0', ['company' => 'Acme Industrial', 'plan' => 'Cumplimiento', 'price' => '$2,500', 'valid_until' => '31 dic 2026'])
        ->where('negotiated.data.1.company', 'Zeta Servicios')
        ->where('negotiated.data.2', ['company' => 'Abierta', 'plan' => 'Cumplimiento', 'price' => '$3,000', 'valid_until' => null])
        ->where('filters', ['q' => '']));
});

it('searches the negotiated prices by the company', function (): void {
    $acme = Tenant::factory()->create(['trade_name' => 'Acme Industrial']);
    Subscription::factory()->create(['tenant_id' => $acme->id, 'negotiated_price_cents' => 250_000]);
    Subscription::factory()->create(['negotiated_price_cents' => 300_000]);

    $this->actingAs(superadmin())->get(route('billing.plans.index', ['q' => 'acme']))->assertInertia(fn (Assert $page) => $page
        ->where('negotiated.total', 1)
        ->where('negotiated.data.0.company', 'Acme Industrial')
        ->where('filters', ['q' => 'acme']));
});

it('pages the negotiated prices 25 at a time', function (): void {
    Subscription::factory()->count(26)->create(['negotiated_price_cents' => 300_000]);

    $this->actingAs(superadmin())->get(route('billing.plans.index'))->assertInertia(fn (Assert $page) => $page
        ->has('negotiated.data', 25)->where('negotiated.last_page', 2));
});

it('reads the page in a fixed number of queries', function (): void {
    Subscription::factory()->count(5)->create(['negotiated_price_cents' => 300_000]);
    $this->actingAs(superadmin());
    $this->expectsDatabaseQueryCount(9);

    $this->get(route('billing.plans.index'))->assertOk();
});
