<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\ListUserTenants;
use App\Modules\Tenancy\Http\Controllers\HomeController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Inertia\Testing\AssertableInertia as Assert;

mutates(HomeController::class, ListUserTenants::class);

it('sends a guest to the login', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('sends the superadmin to the console', function (): void {
    $this->actingAs(superadmin())->get(route('dashboard'))->assertRedirect(route('companies.index'));
});

it('sends a superadmin who is also a member to the console, not to the company', function (): void {
    $user = superadmin();
    TenantMembership::factory()->for(Tenant::factory()->create())->for($user)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('companies.index'));
});

it('tells a person without companies to contact the administrator', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/companies')->where('companies.total', 0));
});

it('sends a person with one company to its panel', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->actingAs(ownerOf($tenant))->get(route('dashboard'))->assertRedirect(route('tenant.dashboard', $tenant));
});

it('lists the companies of a person with two, the blocked one flagged', function (): void {
    $user = User::factory()->create();
    // Created out of name order, so the ordering by trade name is what puts Acme first.
    $blocked = Tenant::factory()->deactivated()->create(['trade_name' => 'Beta Foods', 'slug' => 'beta']);
    $open = Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']);
    TenantMembership::factory()->for($blocked)->for($user)->create();
    TenantMembership::factory()->for($open)->for($user)->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Ajena']))->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/companies')
            ->where('companies.total', 2)
            ->where('companies.data.0.slug', 'acme')
            ->where('companies.data.0.trade_name', 'Acme Industrial')
            ->where('companies.data.0.status', 'not_started')
            ->where('companies.data.0.status_label', 'Sin iniciar')
            ->where('companies.data.0.blocked', false)
            ->where('companies.data.1.slug', 'beta')
            ->where('companies.data.1.status', 'deactivated')
            ->where('companies.data.1.blocked', true));
});

it('does not expose the legal name, the RFC or ids of the companies', function (): void {
    $user = User::factory()->create();
    foreach (['A', 'B'] as $name) {
        TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => $name]))->for($user)->create();
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.data.0', fn (Assert $company) => $company
                ->hasAll(['slug', 'trade_name', 'status', 'status_label', 'blocked'])));
});

it('pages the companies of a person 25 at a time', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 26) as $index) {
        TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => sprintf('Empresa %02d', $index)]))->for($user)->create();
    }

    $this->actingAs($user)
        ->get(route('dashboard', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('companies.total', 26)->has('companies.data', 1)->where('companies.data.0.trade_name', 'Empresa 26'));
});
