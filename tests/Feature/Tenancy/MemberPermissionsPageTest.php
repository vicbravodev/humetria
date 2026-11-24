<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\GrantMemberPermissions;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantType;
use App\Modules\Tenancy\Http\Controllers\MemberController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\CompanyRoles;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(MemberController::class);

// «Permisos adicionales»: what a person has besides their role, by the owner.
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = ownerOf($this->tenant);
    $this->person = User::factory()->create(['name' => 'Beto Ramírez']);
    $this->member = TenantMembership::factory()->for($this->tenant)->for($this->person)->viewer()->create();
});

/** The switch of `$name` among the groups the page sends. */
function memberToggle(iterable $groups, string $name): ?array
{
    foreach ($groups as $group) {
        foreach ($group['permissions'] as $permission) {
            if ($permission['name'] === $name) {
                return $permission;
            }
        }
    }

    return null;
}

it('lives at /admin/{slug}/members/{member}/permissions, and only reading it is left to the staff working as the owner', function (): void {
    expect(route('tenant.members.permissions.edit', ['tenant' => 'acme', 'member' => 7], absolute: false))->toBe('/admin/acme/members/7/permissions')
        ->and(ForbidWhileImpersonating::guards('tenant.members.permissions.update'))->toBeTrue()
        ->and(ForbidWhileImpersonating::guards('tenant.members.permissions.edit'))->toBeFalse();
});

it('shows what the person has, and where each permission comes from', function (): void {
    // A viewer sees vacations by their role; managing them is theirs on their own.
    app(GrantMemberPermissions::class)->handle($this->member, ['view-vacations', 'manage-vacations'], $this->owner);

    $this->actingAs($this->owner)
        ->get(route('tenant.members.permissions.edit', [$this->tenant, $this->member->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/member-permissions')
            ->where('member', ['id' => $this->member->id, 'name' => 'Beto Ramírez', 'email' => $this->person->email, 'role_label' => 'Solo consulta'])
            ->where('groups', fn ($groups): bool => memberToggle($groups, 'view-dashboard')['source'] === 'role'
                && memberToggle($groups, 'view-vacations')['source'] === 'role'
                && memberToggle($groups, 'manage-vacations')['source'] === 'direct'
                && memberToggle($groups, 'manage-attendance')['enabled'] === false)
            ->where('can_manage', true));
});

it('sends the permissions stored for the person themselves, also those their role now gives as well, so saving never drops them', function (): void {
    // view-vacations is stored for the person and the viewer role also brings it: it shows as the role's, and it is still theirs.
    app(GrantMemberPermissions::class)->handle($this->member, ['view-vacations', 'manage-vacations'], $this->owner);

    $this->actingAs($this->owner)
        ->get(route('tenant.members.permissions.edit', [$this->tenant, $this->member->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('direct', ['manage-vacations', 'view-vacations'])
            ->where('groups', fn ($groups): bool => memberToggle($groups, 'view-vacations')['source'] === 'role'));
});

it('leaves out of what it sends the stored permissions of a tool the plan lacks', function (): void {
    app(GrantMemberPermissions::class)->handle($this->member, ['manage-vacations'], $this->owner);
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    $this->actingAs($this->owner)
        ->get(route('tenant.members.permissions.edit', [$this->tenant, $this->member->id]))
        ->assertInertia(fn (Assert $page) => $page->where('direct', []));
});

it('sets the permissions besides the role, and records it', function (): void {
    $this->actingAs($this->owner)
        ->put(route('tenant.members.permissions.update', [$this->tenant, $this->member->id]), ['permissions' => ['manage-vacations']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.members.index', $this->tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Permisos actualizados.']]);

    expect(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $this->tenant))->toBe(['manage-vacations'])
        ->and(AuditEntry::query()->where('event', 'tenant_member.permissions_changed')->sole()->metadata)->toEqual([
            'member_user_id' => $this->person->id, 'added' => ['manage-vacations'], 'removed' => [], 'sensitive' => false,
        ]);

    $this->actingAs($this->owner)
        ->put(route('tenant.members.permissions.update', [$this->tenant, $this->member->id]), ['permissions' => []])
        ->assertSessionHasNoErrors();
    expect(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $this->tenant))->toBe([]);
});

it('asks to acknowledge a sensitive permission it adds, not one the person already had', function (): void {
    $send = fn (array $payload) => $this->actingAs($this->owner)->put(route('tenant.members.permissions.update', [$this->tenant, $this->member->id]), $payload);

    $send(['permissions' => ['handle-complaints']])->assertSessionHasErrors(['acknowledge_sensitive' => 'Confirma que quieres dar permisos que abren información sensible.']);
    expect(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $this->tenant))->toBe([]);

    $send(['permissions' => ['handle-complaints'], 'acknowledge_sensitive' => true])->assertSessionHasNoErrors();
    $send(['permissions' => ['handle-complaints', 'view-vacations']])->assertSessionHasNoErrors();
    expect(AuditEntry::query()->where('event', 'tenant_member.permissions_changed')->oldest('id')->first()->metadata['sensitive'])->toBeTrue();
});

it('refuses a permission the plan does not include, saying why, and one that cannot be handed out', function (string $permission, string $message): void {
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    $this->actingAs($this->owner)
        ->put(route('tenant.members.permissions.update', [$this->tenant, $this->member->id]), ['permissions' => [$permission]])
        ->assertSessionHasErrors(['permissions.0' => $message]);
})->with([
    'a tool the plan lacks' => ['view-vacations', 'Tu plan no incluye Vacaciones.'],
    'the owner\'s own' => ['manage-members', 'Ese permiso no se puede dar en esta empresa.'],
]);

it('finds no person of another company nor the owner: the same 404 as one that does not exist', function (Closure $id): void {
    $id = $id($this);

    $this->actingAs($this->owner)->get(route('tenant.members.permissions.edit', [$this->tenant, $id]))->assertNotFound();
    $this->actingAs($this->owner)->put(route('tenant.members.permissions.update', [$this->tenant, $id]), ['permissions' => ['view-vacations']])->assertNotFound();
})->with([
    'another company\'s' => [fn ($test): int => TenantMembership::factory()->for(Tenant::factory()->create())->viewer()->create()->id],
    'the owner' => [fn ($test): int => TenantMembership::query()->where('user_id', $test->owner->id)->sole()->id],
    'none' => [fn ($test): int => 999999],
]);

it('lets no one but the owner open or change it, and the staff working as the owner only read it', function (): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);
    $this->actingAs($hr)->get(route('tenant.members.permissions.edit', [$this->tenant, $this->member->id]))->assertForbidden();
    $this->actingAs($hr)->put(route('tenant.members.permissions.update', [$this->tenant, $this->member->id]), ['permissions' => ['view-vacations']])->assertForbidden();

    $this->actingAs(support())->post(route('companies.impersonate', $this->tenant))->assertRedirect();
    $this->get(route('tenant.members.permissions.edit', [$this->tenant, $this->member->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_manage', false));
    $this->put(route('tenant.members.permissions.update', [$this->tenant, $this->member->id]), ['permissions' => ['view-vacations']])->assertNotFound();

    expect(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $this->tenant))->toBe([]);
});

it('keeps a permission of a tool the plan no longer includes when the person\'s are saved, and does not record it as removed', function (): void {
    app(GrantMemberPermissions::class)->handle($this->member, ['manage-vacations'], $this->owner);
    $revoked = TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    $this->actingAs($this->owner)
        ->put(route('tenant.members.permissions.update', [$this->tenant, $this->member->id]), ['permissions' => ['manage-attendance']])
        ->assertSessionHasNoErrors();
    $revoked->delete();

    expect(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $this->tenant))->toBe(['manage-attendance', 'manage-vacations'])
        ->and(AuditEntry::query()->where('event', 'tenant_member.permissions_changed')->latest('id')->first()->metadata)->toMatchArray(['added' => ['manage-attendance'], 'removed' => []]);
});

it('does not send a stored permission the account type no longer admits, so saving the rest does not fail and the stored one survives', function (): void {
    app(GrantMemberPermissions::class)->handle($this->member, ['manage-seating'], $this->owner);
    $this->tenant->update(['type' => TenantType::Union]);

    $this->actingAs($this->owner)
        ->get(route('tenant.members.permissions.edit', [$this->tenant, $this->member->id]))
        ->assertInertia(fn (Assert $page) => $page->where('direct', []));

    $this->actingAs($this->owner)
        ->put(route('tenant.members.permissions.update', [$this->tenant, $this->member->id]), ['permissions' => ['manage-compensation'], 'acknowledge_sensitive' => true])
        ->assertSessionHasNoErrors();

    expect(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $this->tenant))->toBe(['manage-compensation', 'manage-seating']);
});

it('answers 404, not an error, to a person id too large to be one', function (): void {
    $this->actingAs($this->owner)->get('/admin/acme/members/99999999999999999999/permissions')->assertNotFound();
    $this->actingAs($this->owner)->put('/admin/acme/members/99999999999999999999/permissions', ['permissions' => []])->assertNotFound();
});
