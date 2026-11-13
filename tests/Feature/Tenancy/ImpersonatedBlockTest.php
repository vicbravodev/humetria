<?php

declare(strict_types=1);

use App\Http\Impersonation;
use App\Modules\Tenancy\Http\Middleware\BlockSuspendedTenant;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(BlockSuspendedTenant::class);

it('gives an impersonating superadmin the real page of a suspended company, told it is blocked, but lets them change nothing', function (): void {
    $effects = 0;
    Route::middleware(['web', 'auth', 'verified', ResolveTenant::class, BlockSuspendedTenant::class])
        ->post('admin/{tenant:slug}/_probe', function () use (&$effects): string {
            $effects++;

            return 'ok';
        })->name('tenant.impersonated-probe');
    Route::getRoutes()->refreshNameLookups();
    $superadmin = superadmin();
    $tenant = Tenant::factory()->suspended()->create();
    ownerOf($tenant);

    $this->actingAs($superadmin)->post(route('companies.impersonate', $tenant));

    $this->get(route('tenant.dashboard', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('tenant.blocked', true)
            ->where('impersonator', $superadmin->name));
    // A suspended company sends nothing and launches nothing, whoever is acting as its owner.
    $this->post(route('tenant.impersonated-probe', $tenant))->assertStatus(423);

    expect($effects)->toBe(0);
});

it('never tells a member of a blocked company they are impersonated', function (): void {
    $tenant = Tenant::factory()->suspended()->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/blocked')
            ->where('tenant.blocked', true)
            ->where('impersonator', null));
});

/**
 * A superadmin, and the owner of two companies: an owner of A who owns B too.
 *
 * @return array{App\Models\User, Tenant, Tenant, App\Models\User}
 */
function twoCompaniesOfOneOwner(): array
{
    $a = Tenant::factory()->create(['trade_name' => 'Empresa A']);
    $b = Tenant::factory()->create(['trade_name' => 'Empresa B']);
    $owner = ownerOf($a);
    TenantMembership::factory()->for($b)->for($owner)->create();

    return [superadmin(), $a, $b, $owner];
}

// Whoever enters as the owner of a company works in that company only, even when that owner also owns another.

it('opens only the company the impersonation started from, and answers 404 for the owner\'s other company', function (): void {
    [$superadmin, $a, $b, $owner] = twoCompaniesOfOneOwner();
    $this->actingAs($superadmin)->post(route('companies.impersonate', $a));

    $this->get(route('tenant.dashboard', $a))->assertOk();
    $this->get(route('tenant.dashboard', $b))->assertNotFound();
});

it('lists only that company in the switcher', function (): void {
    [$superadmin, $a, $b, $owner] = twoCompaniesOfOneOwner();
    $this->actingAs($superadmin)->post(route('companies.impersonate', $a));

    $this->get(route('tenant.dashboard', $a))
        ->assertInertia(fn (Assert $page) => $page->where('tenants.total', 1)->has('tenants.data', 1)->where('tenants.data.0.slug', $a->slug));
});

it('takes the impersonator straight into that company from /admin instead of asking which one', function (): void {
    [$superadmin, $a, $b, $owner] = twoCompaniesOfOneOwner();
    $this->actingAs($superadmin)->post(route('companies.impersonate', $a));

    $this->get(route('dashboard'))->assertRedirect(route('tenant.dashboard', $a));
});

it('forgets the company when the impersonation ends, and gives the superadmin back to the console', function (): void {
    [$superadmin, $a, $b, $owner] = twoCompaniesOfOneOwner();
    $this->actingAs($superadmin)->post(route('companies.impersonate', $a));
    expect(session(Impersonation::TENANT_KEY))->toBe($a->id);

    $this->post(route('impersonation.stop'))->assertRedirect(route('companies.index'));

    expect(session()->has(Impersonation::TENANT_KEY))->toBeFalse();
    $this->assertAuthenticatedAs($superadmin);
});

it('takes the company from the console row, never from the request', function (): void {
    [$superadmin, $a, $b, $owner] = twoCompaniesOfOneOwner();
    $this->actingAs($superadmin)->post(route('companies.impersonate', $a), ['tenant_id' => $b->id]);

    expect(session(Impersonation::TENANT_KEY))->toBe($a->id);
});

it('leaves every company of a person open when nobody is impersonating them', function (): void {
    [$superadmin, $a, $b, $owner] = twoCompaniesOfOneOwner();
    $this->actingAs($owner);

    $this->get(route('tenant.dashboard', $a))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tenants.total', 2));
    $this->get(route('tenant.dashboard', $b))->assertOk();
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('tenancy/companies'));
});

it('ignores a company left in the session once the impersonation is over', function (): void {
    [$superadmin, $a, $b, $owner] = twoCompaniesOfOneOwner();
    $this->actingAs($owner)->withSession([Impersonation::TENANT_KEY => $a->id]);

    $this->get(route('tenant.dashboard', $b))->assertOk();
});
