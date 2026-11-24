<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\HomeController;
use App\Modules\Tenancy\Http\Controllers\NoAccessController;
use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\CompanyRoles;
use App\Modules\Tenancy\Support\StartRoute;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

mutates(StartRoute::class, HomeController::class, TenantDashboardController::class, NoAccessController::class);

/**
 * Where a person lands in a company: the panel for whoever may see it, else the first tool of the menu they may open, else
 * a page that says they have no access yet and whom to ask.
 */
function landingCompanyWith(array $modules): Tenant
{
    $tenant = Tenant::factory()->create();
    resolve(CompanyModules::class)->syncPlan($tenant->id, $modules, CarbonImmutable::now(config('app.display_timezone')));

    return $tenant;
}

/** A member of `$tenant` whose only role is one of the company's own with `$permissions`. */
function landingMemberWithOwnRole(Tenant $tenant, array $permissions): User
{
    $role = app(CreateCompanyRole::class)->handle($tenant, 'Rol propio', $permissions, null, ownerOf($tenant));
    $person = memberOf($tenant, TenantRole::Viewer);
    resolve(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $person->syncRoles([$role]);
    $person->unsetRelation('roles')->unsetRelation('permissions');

    return $person;
}

it('keeps the owner, hr and the viewer on the panel', function (TenantRole $role): void {
    $tenant = Tenant::factory()->create();
    $person = memberOf($tenant, $role);

    $this->actingAs($person)->get(route('dashboard'))->assertRedirect(route('tenant.dashboard', $tenant));
    $this->actingAs($person)->get(route('tenant.dashboard', $tenant))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
})->with([TenantRole::Owner, TenantRole::Hr, TenantRole::Viewer]);

it('sends the Comisión to NOM-037, from /admin and from the panel', function (): void {
    $tenant = Tenant::factory()->create();
    $committee = memberOf($tenant, TenantRole::Committee);

    $this->actingAs($committee)->get(route('dashboard'))->assertRedirect(route('tenant.telework.index', $tenant));
    $this->actingAs($committee)->get(route('tenant.dashboard', $tenant))->assertRedirect(route('tenant.telework.index', $tenant));
});

it('sends the handler of complaints to the cases, from /admin and from the panel', function (): void {
    $tenant = Tenant::factory()->create();
    $handler = memberOf($tenant, TenantRole::Complaints);

    $this->actingAs($handler)->get(route('dashboard'))->assertRedirect(route('tenant.complaints.index', $tenant));
    $this->actingAs($handler)->get(route('tenant.dashboard', $tenant))->assertRedirect(route('tenant.complaints.index', $tenant));
});

it('keeps on the panel a member of the Comisión who was also given the work centers', function (): void {
    $tenant = Tenant::factory()->create();
    $committee = memberOf($tenant, TenantRole::Committee);
    app(CompanyRoles::class)->syncDirectPermissions($committee, $tenant, ['view-work-centers']);

    $this->actingAs($committee)->get(route('dashboard'))->assertRedirect(route('tenant.dashboard', $tenant));
    $this->actingAs($committee)->get(route('tenant.dashboard', $tenant))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

it('sends a role of the company\'s own that only sees vacations to the vacations', function (): void {
    $tenant = Tenant::factory()->create();
    $person = landingMemberWithOwnRole($tenant, ['view-vacations']);

    expect(app(StartRoute::class)->for($person, $tenant))->toBe('tenant.vacations.index');
    $this->actingAs($person)->get(route('dashboard'))->assertRedirect(route('tenant.vacations.index', $tenant));
});

it('follows the order of the menu: NOM-037 before the vacations', function (): void {
    $tenant = Tenant::factory()->create();

    expect(app(StartRoute::class)->for(landingMemberWithOwnRole($tenant, ['view-vacations', 'view-telework']), $tenant))
        ->toBe('tenant.telework.index');
});

it('sends a manager of the channel who handles no cases to the channel', function (): void {
    $tenant = Tenant::factory()->create();

    expect(app(StartRoute::class)->for(landingMemberWithOwnRole($tenant, ['manage-complaint-channel']), $tenant))
        ->toBe('tenant.complaints.channel.show');
});

it('skips a tool whose module the company does not have', function (): void {
    $tenant = Tenant::factory()->create();
    $person = landingMemberWithOwnRole($tenant, ['view-telework', 'view-vacations']);
    resolve(CompanyModules::class)->syncPlan($tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));

    expect(app(StartRoute::class)->for($person, $tenant))->toBe('tenant.vacations.index');
});

it('does not send a person with only a permission to change something to a page that needs another to look', function (): void {
    $tenant = Tenant::factory()->create();

    expect(app(StartRoute::class)->for(landingMemberWithOwnRole($tenant, ['manage-vacations']), $tenant))->toBe('tenant.no-access');
});

it('sends a role with no permissions to the page without access', function (): void {
    $tenant = Tenant::factory()->create();

    expect(app(StartRoute::class)->for(landingMemberWithOwnRole($tenant, []), $tenant))->toBe('tenant.no-access');
});

it('tells a person with nothing to open whom to ask, without sending them in a loop', function (): void {
    // The Comisión of a company with none of its tools has nothing left but the panel, which it does not land on.
    $tenant = landingCompanyWith(['vacations']);
    $owner = ownerOf($tenant);
    $owner->forceFill(['name' => 'Rita Dueña'])->save();
    $committee = memberOf($tenant, TenantRole::Committee);

    $this->actingAs($committee)->get(route('dashboard'))->assertRedirect(route('tenant.no-access', $tenant));
    $this->actingAs($committee)->get(route('tenant.dashboard', $tenant))->assertRedirect(route('tenant.no-access', $tenant));
    $this->actingAs($committee)->get(route('tenant.no-access', $tenant))
        ->assertOk()
        ->assertDontSee($owner->email)
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/no-access')->where('ownerName', 'Rita Dueña'));
    $this->actingAs($committee)->followingRedirects()->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/no-access'));
});

it('shows on the panel only the standards the person may see', function (): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->for($tenant)->create();
    $committee = memberOf($tenant, TenantRole::Committee);
    app(CompanyRoles::class)->syncDirectPermissions($committee, $tenant, ['view-work-centers']);

    $this->actingAs($committee)->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('norms', ['nom037', 'seating'])
            ->missing('centers.data.0.nom035')
            ->has('centers.data.0.nom037')
            ->where('week', []));
    $this->actingAs(memberOf($tenant, TenantRole::Viewer))->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('norms', ['nom035', 'nom037', 'seating'])
            ->has('centers.data.0.nom035'));
});
