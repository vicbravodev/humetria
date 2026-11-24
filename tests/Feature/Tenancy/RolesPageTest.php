<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\RoleController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\CompanyRoles;
use App\Modules\Tenancy\Support\RoutePermissions;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

mutates(RoleController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = ownerOf($this->tenant);
});

/** A role of the company's own, made of `$permissions`. */
function companyRole(Tenant $tenant, User $by, string $label = 'Auditoría', array $permissions = ['view-dashboard', 'view-climate']): Role
{
    return app(CreateCompanyRole::class)->handle($tenant, $label, $permissions, null, $by);
}

/** A person of the company who holds `$role`. */
function personHolding(Tenant $tenant, Role $role): User
{
    $person = User::factory()->create();
    TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();
    resolve(CompanyRoles::class)->assign($person, $tenant, $role);

    return $person;
}

/** Signs a staff member in as the company's owner, from the console. */
function impersonatingOwnerOf(Tenant $tenant): void
{
    test()->actingAs(support())->post(route('companies.impersonate', $tenant))->assertRedirect();
}

it('lives at /admin/{slug}/roles, only for the owner, and impersonating changes nothing there', function (): void {
    expect(route('tenant.roles.index', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/roles')
        ->and(collect(['tenant.roles.index', 'tenant.roles.create', 'tenant.roles.store', 'tenant.roles.edit', 'tenant.roles.update', 'tenant.roles.destroy'])
            ->map(RoutePermissions::for(...))->unique()->all())->toBe(['manage-members'])
        ->and(collect(['tenant.roles.create', 'tenant.roles.store', 'tenant.roles.update', 'tenant.roles.destroy'])->every(ForbidWhileImpersonating::guards(...)))->toBeTrue()
        ->and(ForbidWhileImpersonating::guards('tenant.roles.index'))->toBeFalse()
        ->and(ForbidWhileImpersonating::guards('tenant.roles.edit'))->toBeFalse();
});

it('lists the templates the company hands out and its own roles, with how many people and open invitations each has here', function (): void {
    $audit = companyRole($this->tenant, $this->owner);
    $payroll = companyRole($this->tenant, $this->owner, 'Nómina', ['view-compensation']);
    personHolding($this->tenant, $audit);
    personHolding($this->tenant, $audit);
    TenantInvitation::factory()->for($this->tenant)->role($payroll)->create();
    TenantInvitation::factory()->for($this->tenant)->role($payroll)->cancelled()->create();
    memberOf($this->tenant, TenantRole::Viewer);
    // People of another company with the same template are not counted here.
    memberOf(Tenant::factory()->create(), TenantRole::Viewer);
    companyRole($other = Tenant::factory()->create(), ownerOf($other), 'De otra');

    $this->actingAs($this->owner)
        ->get(route('tenant.roles.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/roles')
            ->where('templates', fn ($templates): bool => collect($templates)->map(fn ($row): array => [$row['key'], $row['label'], $row['people']])->all() === [
                ['hr', 'Ayuda con las rutas', 0],
                ['viewer', 'Solo consulta', 1],
                ['committee', 'Comisión de seguridad e higiene', 0],
                ['complaints', 'Atención de quejas', 0],
            ] && collect($templates)->every(fn ($row): bool => $row['permissions'] > 0))
            ->where('roles.total', 2)
            ->where('roles.data', [
                ['id' => $audit->id, 'label' => 'Auditoría', 'based_on' => null, 'people' => 2, 'invitations' => 0, 'permissions' => 2],
                ['id' => $payroll->id, 'label' => 'Nómina', 'based_on' => null, 'people' => 0, 'invitations' => 1, 'permissions' => 1],
            ])
            ->where('search', '')
            ->where('limit', ['used' => 2, 'max' => 20])
            ->where('can_manage', true));
});

it('says which template a role was copied from', function (): void {
    $role = app(CreateCompanyRole::class)->handle($this->tenant, 'RH de planta', ['view-dashboard'], TenantRole::Hr, $this->owner);

    $this->actingAs($this->owner)
        ->get(route('tenant.roles.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('roles.data.0.id', $role->id)->where('roles.data.0.based_on', 'Ayuda con las rutas'));
});

it('searches the company\'s roles by their name on the server, ignoring accents, and pages them', function (): void {
    companyRole($this->tenant, $this->owner, 'Auditoría interna');
    companyRole($this->tenant, $this->owner, 'Nómina');

    $this->actingAs($this->owner)
        ->get(route('tenant.roles.index', [$this->tenant, 'q' => 'auditoria']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', 'auditoria')
            ->where('roles.total', 1)
            ->where('roles.data.0.label', 'Auditoría interna'));

    config(['tenancy.max_custom_roles' => 30]);
    foreach (range(1, 25) as $index) {
        companyRole($this->tenant, $this->owner, sprintf('Zeta %02d', $index));
    }

    $this->actingAs($this->owner)
        ->get(route('tenant.roles.index', [$this->tenant, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('roles.total', 27)->has('roles.data', 2)->where('roles.data.1.label', 'Zeta 25'));
});

it('is only for the owner', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.roles.index', $this->tenant))->assertForbidden();
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('answers 404 to the owner of another company', function (): void {
    $this->actingAs(ownerOf(Tenant::factory()->create()))->get(route('tenant.roles.index', $this->tenant))->assertNotFound();
});

it('shows the roles to the staff working as the owner, only to read', function (): void {
    companyRole($this->tenant, $this->owner);
    impersonatingOwnerOf($this->tenant);

    $this->get(route('tenant.roles.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/roles')->where('roles.total', 1)->where('can_manage', false));
});

it('reads the list in the same number of queries however many roles and people there are', function (int $roles): void {
    foreach (range(1, $roles) as $index) {
        $role = companyRole($this->tenant, $this->owner, "Rol {$index}");
        personHolding($this->tenant, $role);
        TenantInvitation::factory()->for($this->tenant)->role($role)->create();
    }

    $this->actingAs($this->owner->fresh());
    $this->expectsDatabaseQueryCount(QUERIES_OF_THE_ROLES_PAGE);

    $this->get(route('tenant.roles.index', $this->tenant))->assertOk();
})->with([1, 4]);

// Measured, then fixed so that a query per role breaks the test.
const QUERIES_OF_THE_ROLES_PAGE = 17;

it('sends only roles and counts: nobody\'s name or email', function (): void {
    $role = companyRole($this->tenant, $this->owner);
    $person = personHolding($this->tenant, $role);
    $person->forceFill(['name' => 'Rosa Secreta', 'email' => 'rosa@acme.mx'])->save();
    DB::table('users')->where('id', $this->owner->id)->update(['name' => 'Olga Dueña']);

    $response = $this->actingAs($this->owner)->get(route('tenant.roles.index', $this->tenant));

    expect(json_encode($response->viewData('page')['props']['roles']))->not->toContain('Rosa')->not->toContain('rosa@acme.mx')
        ->and(json_encode($response->viewData('page')['props']['templates']))->not->toContain('Olga');
});

/** The switch of `$name` among the groups an editor page sends. */
function toggleOf(array $groups, string $name): ?array
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

it('opens the editor of a new role with every permission the company can hand out, by tool', function (): void {
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    $this->actingAs($this->owner)
        ->get(route('tenant.roles.create', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/role-editor')
            ->where('role', null)
            ->where('from', null)
            ->where('templates', [
                ['key' => 'hr', 'label' => 'Ayuda con las rutas'],
                ['key' => 'viewer', 'label' => 'Solo consulta'],
                ['key' => 'committee', 'label' => 'Comisión de seguridad e higiene'],
                ['key' => 'complaints', 'label' => 'Atención de quejas'],
            ])
            ->where('groups.0.module', 'general')
            ->where('groups.0.label', 'General')
            ->where('groups.0.permissions.0', [
                'name' => 'view-dashboard', 'label' => 'Ver el tablero', 'description' => 'Abrir el tablero con el resumen de la empresa.',
                'sensitive' => false, 'enabled' => false, 'unavailableReason' => null, 'source' => null,
            ])
            ->where('groups', fn ($groups): bool => collect($groups)->pluck('label')->all() === ['General', 'NOM-035', 'NOM-037', 'Ley Silla', 'Quejas', 'Asistencia', 'Vacaciones', 'Organigrama', 'Pulso', 'Clima', 'Compensación']
                // The owner's own permission is never a switch.
                && toggleOf($groups->toArray(), 'manage-members') === null
                && toggleOf($groups->toArray(), 'view-vacations')['unavailableReason'] === 'Tu plan no incluye Vacaciones.'
                && toggleOf($groups->toArray(), 'view-trauma-screenings')['sensitive'] === true)
            ->where('can_manage', true));
});

it('starts a new role from a template, with what the template has switched on', function (): void {
    $this->actingAs($this->owner)
        ->get(route('tenant.roles.create', [$this->tenant, 'from' => 'committee']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('from', 'committee')
            ->where('groups', function ($groups): bool {
                $on = collect($groups)->flatMap(fn ($group) => $group['permissions'])->where('enabled', true);

                return $on->pluck('name')->sort()->values()->all() === Role::findByName('committee')->permissions->pluck('name')->sort()->values()->all()
                    && $on->every(fn ($permission): bool => $permission['source'] === 'role');
            }));

    // Neither the owner's nor an unknown template.
    $this->actingAs($this->owner)
        ->get(route('tenant.roles.create', [$this->tenant, 'from' => 'owner']))
        ->assertInertia(fn (Assert $page) => $page->where('from', null));
});

it('shows a union only the switches of compensation', function (): void {
    $union = Tenant::factory()->union()->create();

    $this->actingAs(ownerOf($union))
        ->get(route('tenant.roles.create', $union))
        ->assertInertia(fn (Assert $page) => $page->where('groups', fn ($groups): bool => collect($groups)->pluck('module')->all() === ['compensation']));
});

it('creates a role of the company and records it, without anything personal', function (): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.roles.store', $this->tenant), ['label' => ' Auditoría ', 'permissions' => ['view-dashboard', 'view-climate'], 'based_on' => 'viewer'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.roles.index', $this->tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Rol creado.']]);

    $role = Role::query()->where('tenant_id', $this->tenant->id)->sole();
    expect($role->getAttribute('label'))->toBe('Auditoría')
        ->and($role->getAttribute('based_on'))->toBe('viewer')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['view-climate', 'view-dashboard'])
        ->and(AuditEntry::query()->where('event', 'tenant_role.created')->sole()->metadata)->toEqual([
            'label' => 'Auditoría', 'based_on' => 'viewer', 'permissions' => ['view-dashboard', 'view-climate'], 'sensitive' => false,
        ]);
});

it('asks to acknowledge a sensitive permission before giving it, and records that it was', function (): void {
    $payload = ['label' => 'Nómina', 'permissions' => ['view-dashboard', 'view-compensation']];

    $this->actingAs($this->owner)
        ->post(route('tenant.roles.store', $this->tenant), $payload)
        ->assertSessionHasErrors(['acknowledge_sensitive' => 'Confirma que quieres dar permisos que abren información sensible.']);
    expect(Role::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();

    $this->actingAs($this->owner)
        ->post(route('tenant.roles.store', $this->tenant), [...$payload, 'acknowledge_sensitive' => true])
        ->assertSessionHasNoErrors();
    expect(AuditEntry::query()->where('event', 'tenant_role.created')->sole()->metadata['sensitive'])->toBeTrue();
});

it('refuses a permission the plan does not include, saying why, and one that cannot be handed out', function (string $permission, string $message): void {
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    $this->actingAs($this->owner)
        ->post(route('tenant.roles.store', $this->tenant), ['label' => 'Auditoría', 'permissions' => ['view-dashboard', $permission]])
        ->assertSessionHasErrors(['permissions.1' => $message]);
    expect(Role::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
})->with([
    'a tool the plan lacks' => ['view-vacations', 'Tu plan no incluye Vacaciones.'],
    'the owner\'s own' => ['manage-members', 'Ese permiso no se puede dar en esta empresa.'],
    'one that does not exist' => ['delete-everything', 'Ese permiso no se puede dar en esta empresa.'],
]);

it('refuses a role without a name or without permissions', function (array $payload, string $field): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.roles.store', $this->tenant), [...['label' => 'Auditoría', 'permissions' => ['view-dashboard']], ...$payload])
        ->assertSessionHasErrors($field);
})->with([
    'no name' => [['label' => '  '], 'label'],
    'a name too long' => [['label' => str_repeat('a', 61)], 'label'],
    'no permission' => [['permissions' => []], 'permissions'],
    'a template that is not handed out' => [['based_on' => 'owner'], 'based_on'],
]);

it('takes a name only once in the company, a template\'s included, ignoring case', function (string $label): void {
    companyRole($this->tenant, $this->owner, 'Auditoría');

    $this->actingAs($this->owner)
        ->post(route('tenant.roles.store', $this->tenant), ['label' => $label, 'permissions' => ['view-dashboard']])
        ->assertSessionHasErrors(['label' => 'Ya hay un rol con ese nombre.']);
})->with(['AUDITORÍA', 'solo consulta']);

it('takes a name another company already uses', function (): void {
    companyRole($other = Tenant::factory()->create(), ownerOf($other), 'Auditoría');

    $this->actingAs($this->owner)
        ->post(route('tenant.roles.store', $this->tenant), ['label' => 'Auditoría', 'permissions' => ['view-dashboard']])
        ->assertSessionHasNoErrors();
});

it('says when the company already has as many roles of its own as it may', function (): void {
    config(['tenancy.max_custom_roles' => 1]);
    companyRole($this->tenant, $this->owner, 'Auditoría');

    $this->actingAs($this->owner)
        ->post(route('tenant.roles.store', $this->tenant), ['label' => 'Nómina', 'permissions' => ['view-dashboard']])
        ->assertSessionHasErrors(['label' => 'Ya tienes 1 roles propios, el máximo. Borra uno que no uses para crear otro.']);
});

it('creates no role for anyone but the owner, nor for the staff working as the owner', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.roles.create', $this->tenant))->assertForbidden();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->post(route('tenant.roles.store', $this->tenant), ['label' => 'X', 'permissions' => ['view-dashboard']])->assertForbidden();

    impersonatingOwnerOf($this->tenant);
    $this->get(route('tenant.roles.create', $this->tenant))->assertNotFound();
    $this->post(route('tenant.roles.store', $this->tenant), ['label' => 'X', 'permissions' => ['view-dashboard']])->assertNotFound();

    expect(Role::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});
