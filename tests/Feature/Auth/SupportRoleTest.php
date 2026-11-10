<?php

declare(strict_types=1);

use App\Http\Impersonation;
use App\Http\Middleware\EndRevokedImpersonation;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

mutates(User::class, Impersonation::class, EndRevokedImpersonation::class);

it('has the platform role `support` from the start, with no company', function (): void {
    expect(Role::query()->where('name', User::SUPPORT)->sole()->guard_name)->toBe('web');
});

it('is staff only with the platform role, outside any company', function (): void {
    $tenant = Tenant::factory()->create();
    $inACompany = User::factory()->create();
    setPermissionsTeamId($tenant->id);
    $inACompany->assignRole(User::SUPPORT);
    setPermissionsTeamId(null);

    expect(support()->isSupport())->toBeTrue()
        ->and(support()->isStaff())->toBeTrue()
        ->and(support()->isSuperadmin())->toBeFalse()
        ->and(superadmin()->isStaff())->toBeTrue()
        ->and(superadmin()->isSupport())->toBeFalse()
        ->and($inACompany->isSupport())->toBeFalse()
        ->and($inACompany->isStaff())->toBeFalse()
        ->and(User::factory()->create()->isStaff())->toBeFalse();
});

it('does not give support the superadmin pass: Gate::before is still the superadmin\'s', function (): void {
    Gate::define('anything', fn (User $user): bool => false);

    expect(Gate::forUser(support())->allows('anything'))->toBeFalse()
        ->and(Gate::forUser(superadmin())->allows('anything'))->toBeTrue();
});

it('lets support see the console and the demo requests, and not anyone else', function (): void {
    Tenant::factory()->create(['trade_name' => 'Acme']);

    $this->actingAs(support())->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/index')->has('tenants.data', 1));
    $this->actingAs(support())->get(route('demo-requests.index'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('companies.index'))->assertNotFound();
});

it('shares who is staff, so the panel shows the console to support', function (): void {
    $this->actingAs(support())->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isStaff', true)->where('auth.isSuperadmin', false));
    $this->actingAs(superadmin())->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isStaff', true)->where('auth.isSuperadmin', true));
});

it('starts support in the console, as the superadmin', function (): void {
    $this->actingAs(support())->get(route('dashboard'))->assertRedirect(route('companies.index'));
});

it('refuses support every change to a company with a 404 that reveals nothing, whatever the body', function (string $method, string $route): void {
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::factory()->for($tenant)->create();
    $parameters = match (true) {
        $route === 'companies.store' => [],
        str_contains($route, 'invitations.') && $route !== 'companies.invitations.store' => ['tenant' => $tenant, 'invitation' => $invitation],
        default => ['tenant' => $tenant],
    };

    $this->actingAs(support())
        ->call($method, route($route, $parameters), ['restriction' => 'suspended', 'reason' => 'Prueba'])
        ->assertNotFound();

    expect($tenant->refresh()->restriction)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'like', 'tenant.%')->count())->toBe(0);
})->with([
    'create' => ['POST', 'companies.store'],
    'edit' => ['PUT', 'companies.update'],
    'activate' => ['POST', 'companies.activate'],
    'restrict' => ['POST', 'companies.restrict'],
    'reactivate' => ['POST', 'companies.reactivate'],
    'set the logo' => ['POST', 'companies.logo.update'],
    'remove the logo' => ['DELETE', 'companies.logo.destroy'],
    'invite the owner' => ['POST', 'companies.invitations.store'],
    'resend an invitation' => ['POST', 'companies.invitations.resend'],
    'cancel an invitation' => ['DELETE', 'companies.invitations.cancel'],
    'Humi settings' => ['PUT', 'companies.assistant.update'],
]);

it('lets support work as the owner of a company, and come back to its own session', function (): void {
    $support = support();
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);

    $this->actingAs($support)->post(route('companies.impersonate', $tenant))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($owner);

    $this->get(route('tenant.dashboard', $tenant))->assertOk();
    $this->assertAuthenticatedAs($owner);

    $this->post(route('impersonation.stop'))->assertRedirect(route('companies.index'));
    $this->assertAuthenticatedAs($support);

    expect(AuditEntry::query()->where('event', 'impersonation.started')->sole()->impersonator_id)->toBe($support->id);
});

it('never lets support enter as another member of the staff', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);
    setPermissionsTeamId(null);
    $owner->assignRole(User::SUPPORT);

    $this->actingAs(support())->post(route('companies.impersonate', $tenant))->assertNotFound();
});

it('ends an impersonation whose staff member lost the role', function (): void {
    $support = support();
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);
    $this->actingAs($support)->post(route('companies.impersonate', $tenant));

    setPermissionsTeamId(null);
    $support->removeRole(User::SUPPORT);

    $this->get(route('tenant.dashboard', $tenant))->assertRedirect();
    $this->assertGuest();
    expect($owner->refresh()->id)->toBe($owner->id);
});

it('keeps support out of the queue dashboard and out of what it does as the owner of a company', function (): void {
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);

    expect(Gate::forUser(support())->allows('viewHorizon'))->toBeFalse();

    $this->actingAs(support())->post(route('companies.impersonate', $tenant));
    $this->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => 'Prueba'])->assertNotFound();
});
