<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Inertia\Testing\AssertableInertia as Assert;

mutates(TenantDashboardController::class);

// /admin/{slug} is the company's front door (sign-in by SSO, the company switcher, Humi's links): whoever may not open the panel
// is sent where they land, never shown a 403.

/** A person of `$tenant` whose role is one of the company's own with only `$permissions`. */
function holderOfOwnRole(Tenant $tenant, array $permissions): User
{
    $role = app(CreateCompanyRole::class)->handle($tenant, 'Propio '.count($permissions), $permissions, null, ownerOf($tenant));
    $person = User::factory()->create();
    TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();
    resolve(CompanyRoles::class)->assign($person, $tenant, $role);

    return $person;
}

it('sends a role with only the vacations from the company\'s address to its vacations', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->actingAs(holderOfOwnRole($tenant, ['view-vacations']))
        ->get('/admin/acme')
        ->assertRedirect('/admin/acme/vacations');
});

it('sends a role that opens nothing of the menu to the page without access, without a loop', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->actingAs(holderOfOwnRole($tenant, ['use-assistant']))
        ->get('/admin/acme')
        ->assertRedirect(route('tenant.no-access', $tenant));
});

it('keeps the owner and RH on the panel', function (TenantRole $role): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $person = $role === TenantRole::Owner ? ownerOf($tenant) : memberOf($tenant, $role);

    $this->actingAs($person)
        ->get('/admin/acme')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
})->with([TenantRole::Owner, TenantRole::Hr]);
