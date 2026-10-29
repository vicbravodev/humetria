<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Http\Controllers\HomeController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Inertia\Testing\AssertableInertia as Assert;

mutates(HomeController::class);

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
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/companies')->has('companies', 0));
});

it('sends a person with one company to its panel', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->actingAs(ownerOf($tenant))->get(route('dashboard'))->assertRedirect(route('tenant.dashboard', $tenant));
});

it('lists the companies of a person with two, the blocked one flagged', function (): void {
    $user = User::factory()->create();
    $open = Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']);
    $blocked = Tenant::factory()->deactivated()->create(['trade_name' => 'Beta Foods', 'slug' => 'beta']);
    TenantMembership::factory()->for($open)->for($user)->create();
    TenantMembership::factory()->for($blocked)->for($user)->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Ajena']))->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/companies')
            ->has('companies', 2)
            ->where('companies.0.slug', 'acme')
            ->where('companies.0.trade_name', 'Acme Industrial')
            ->where('companies.0.status', 'not_started')
            ->where('companies.0.status_label', 'Sin iniciar')
            ->where('companies.0.blocked', false)
            ->where('companies.1.slug', 'beta')
            ->where('companies.1.status', 'deactivated')
            ->where('companies.1.blocked', true));
});

it('does not expose the legal name, the RFC or ids of the companies', function (): void {
    $user = User::factory()->create();
    foreach (['A', 'B'] as $name) {
        TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => $name]))->for($user)->create();
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.0', fn (Assert $company) => $company
                ->hasAll(['slug', 'trade_name', 'status', 'status_label', 'blocked'])));
});
