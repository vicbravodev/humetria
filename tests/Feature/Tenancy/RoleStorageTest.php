<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

mutates(CompanyRoles::class);

/**
 * Company roles are Spatie roles assigned inside a team, the company. The platform's superadmin is a role with no team.
 */
it('defines the company roles and what each one allows', function (): void {
    $permissions = fn (string $role): array => Role::findByName($role)->permissions->pluck('name')->sort()->values()->all();

    expect($permissions('owner'))->toBe(['manage-campaigns', 'manage-members', 'manage-organization', 'manage-telework', 'review-workplaces', 'view-telework', 'view-trauma-screenings'])
        ->and($permissions('hr'))->toBe(['manage-campaigns', 'manage-organization', 'manage-telework', 'review-workplaces', 'view-telework', 'view-trauma-screenings'])
        ->and($permissions('viewer'))->toBe(['view-telework'])
        ->and($permissions('committee'))->toBe(['review-workplaces', 'view-telework']);
});

it('keeps each person\'s role per company: hr in one, viewer in another', function (): void {
    $user = User::factory()->create();
    [$acme, $beta] = Tenant::factory()->count(2)->create()->all();
    TenantMembership::factory()->for($acme)->for($user)->role(TenantRole::Hr)->create();
    TenantMembership::factory()->for($beta)->for($user)->role(TenantRole::Viewer)->create();

    $roles = resolve(CompanyRoles::class);

    expect($roles->of($user, $acme))->toBe(TenantRole::Hr)
        ->and($roles->of($user, $beta))->toBe(TenantRole::Viewer)
        ->and($roles->of($user, Tenant::factory()->create()))->toBeNull()
        ->and(DB::table('tenant_memberships')->where('user_id', $user->id)->count())->toBe(2);
});

it('gives a membership the owner role unless told otherwise', function (): void {
    $membership = TenantMembership::factory()->create();

    expect(resolve(CompanyRoles::class)->of($membership->user()->sole(), $membership->tenant()->sole()))->toBe(TenantRole::Owner);
});

it('changes a role inside one company without touching the others or the superadmin role', function (): void {
    $user = superadmin();
    [$acme, $beta] = Tenant::factory()->count(2)->create()->all();
    TenantMembership::factory()->for($acme)->for($user)->role(TenantRole::Hr)->create();
    TenantMembership::factory()->for($beta)->for($user)->role(TenantRole::Hr)->create();
    $roles = resolve(CompanyRoles::class);

    $roles->assign($user, $acme, TenantRole::Viewer);

    expect($roles->of($user, $acme))->toBe(TenantRole::Viewer)
        ->and($roles->of($user, $beta))->toBe(TenantRole::Hr)
        ->and($user->fresh()?->hasRole(User::SUPERADMIN))->toBeTrue();
});

it('removes the role of a person in one company only', function (): void {
    $user = User::factory()->create();
    [$acme, $beta] = Tenant::factory()->count(2)->create()->all();
    TenantMembership::factory()->for($acme)->for($user)->role(TenantRole::Hr)->create();
    TenantMembership::factory()->for($beta)->for($user)->role(TenantRole::Hr)->create();
    $roles = resolve(CompanyRoles::class);

    $roles->remove($user, $acme);

    expect($roles->of($user, $acme))->toBeNull()
        ->and($roles->of($user, $beta))->toBe(TenantRole::Hr);
});

it('lists the role of everyone in a company with one query', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = TenantMembership::factory()->for($tenant)->create();
    $hr = TenantMembership::factory()->for($tenant)->role(TenantRole::Hr)->create();
    TenantMembership::factory()->role(TenantRole::Viewer)->create();

    $this->expectsDatabaseQueryCount(1);

    expect(resolve(CompanyRoles::class)->inCompany($tenant))->toBe([$owner->user_id => TenantRole::Owner, $hr->user_id => TenantRole::Hr]);
});

it('allows a single owner per company in the database itself', function (): void {
    $tenant = Tenant::factory()->create();
    TenantMembership::factory()->for($tenant)->create();

    TenantMembership::factory()->for($tenant)->create();
})->throws(UniqueConstraintViolationException::class, 'model_has_roles_one_owner_per_company');

it('lets a company have several open member invitations, one per email', function (): void {
    $tenant = Tenant::factory()->create();
    TenantInvitation::factory()->for($tenant)->create();
    TenantInvitation::factory()->for($tenant)->role(TenantRole::Hr)->create(['email' => 'ana@acme.mx']);
    TenantInvitation::factory()->for($tenant)->role(TenantRole::Viewer)->create(['email' => 'luis@acme.mx']);

    expect(fn () => TenantInvitation::factory()->for($tenant)->role(TenantRole::Viewer)->create(['email' => 'ana@acme.mx']))
        ->toThrow(UniqueConstraintViolationException::class, 'tenant_invitations_one_open_per_email');
});

it('still allows a single open owner invitation per company', function (): void {
    $tenant = Tenant::factory()->create();
    TenantInvitation::factory()->for($tenant)->create();

    expect(fn () => TenantInvitation::factory()->for($tenant)->create())
        ->toThrow(UniqueConstraintViolationException::class, 'tenant_invitations_one_open_owner');
});

it('rejects an unknown role in an invitation', function (): void {
    expect(fn () => DB::table('tenant_invitations')->insert([
        ...TenantInvitation::factory()->raw(['tenant_id' => Tenant::factory()->create()->id, 'role' => 'admin']),
        'id' => fake()->uuid(),
    ]))->toThrow(QueryException::class, 'tenant_invitations_role_known');
});

it('forgets who can review as soon as someone loses their role', function (): void {
    $tenant = Tenant::factory()->create();
    $cora = memberOf($tenant, TenantRole::Committee);
    workIn($tenant);
    $members = resolve(App\Modules\Tenancy\Contracts\Members::class);
    expect(array_map(fn ($member): int => $member->userId, $members->reviewers()))->toBe([$cora->id]);

    resolve(CompanyRoles::class)->remove($cora, $tenant);

    expect($members->reviewers())->toBe([]);
});
