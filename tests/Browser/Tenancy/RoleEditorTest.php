<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Spatie\Permission\Models\Role;

/** Signs in the owner of Acme and makes a role of its own, held by `$people` people. */
function acmeRoleHeldBy(int $people, string $label = 'Auditoría'): Role
{
    $owner = User::factory()->create();
    signInToPanel($owner);
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $role = app(CreateCompanyRole::class)->handle($tenant, $label, ['view-dashboard', 'view-vacations'], null, $owner);

    for ($held = 0; $held < $people; $held++) {
        $person = User::factory()->create();
        TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();
        resolve(CompanyRoles::class)->assign($person, $tenant, $role);
    }

    return $role;
}

it('creates a role with a sensitive permission only after the owner confirms it', function (): void {
    signInToPanel();

    visit('/admin/acme/roles/create')
        ->type('label', 'Canalización')
        ->click('@permission-view-trauma-screenings')
        ->assertSee('Vas a dar permisos que abren información sensible: Ver tamizajes de la Guía I.')
        ->press('Crear rol')
        ->assertSee('Confirma que quieres dar permisos que abren información sensible.')
        ->click('Entiendo y confirmo')
        ->press('Crear rol')
        ->assertSee('Rol creado.')
        ->assertSee('Canalización')
        ->assertNoJavaScriptErrors();

    $role = Role::query()->where('label', 'Canalización')->sole();
    expect($role->permissions->pluck('name')->all())->toBe(['view-trauma-screenings']);
});

it('starts a new role from a template, with what the template has switched on', function (): void {
    signInToPanel();

    visit('/admin/acme/roles')
        ->click('[aria-label="Copiar y ajustar Solo consulta"]')
        ->assertSee('Nuevo rol')
        ->assertSeeIn('#role-start', 'Copia de Solo consulta')
        ->assertAttribute('@permission-view-dashboard', 'aria-checked', 'true')
        ->assertAttribute('@permission-manage-campaigns', 'aria-checked', 'false')
        ->assertNoJavaScriptErrors();
});

it('says a role someone holds cannot be deleted, and deletes one nobody holds', function (): void {
    acmeRoleHeldBy(1);

    visit('/admin/acme/roles')
        ->click('[aria-label="Editar Auditoría"]')
        ->assertSee('Una persona tiene este rol.')
        ->press('Borrar rol')
        ->assertSee('No puedes borrarlo: tiene 1 persona.')
        ->assertNoJavaScriptErrors();

    $free = app(CreateCompanyRole::class)->handle(Tenant::query()->where('slug', 'acme')->sole(), 'Temporal', ['view-dashboard'], null, User::query()->orderBy('id')->firstOrFail());

    visit("/admin/acme/roles/{$free->id}/edit")
        ->press('Borrar rol')
        ->assertSee('Nadie lo tiene.')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Rol borrado.')
        ->assertDontSee('Temporal');
});

it('gives a person a permission besides their role, keeping the role\'s on and locked', function (): void {
    signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $person = User::factory()->create(['name' => 'Vera Vista']);
    $member = TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();

    visit("/admin/acme/members/{$member->id}/permissions")
        ->assertSee('Permisos adicionales de Vera Vista')
        ->assertAttribute('@permission-view-dashboard', 'aria-checked', 'true')
        ->assertAttribute('@permission-view-dashboard', 'disabled', '')
        ->click('@permission-manage-vacations')
        ->press('Guardar permisos')
        ->assertSee('Permisos actualizados.')
        ->assertNoJavaScriptErrors();

    expect(resolve(CompanyRoles::class)->directPermissionsOf($person, $tenant))->toBe(['manage-vacations']);
});

it('offers no new role nor a copy of a template once the company has as many roles as it may', function (): void {
    config(['tenancy.max_custom_roles' => 1]);
    acmeRoleHeldBy(0);

    visit('/admin/acme/roles')
        ->assertSee('Llegaste al máximo de 1 roles propios.')
        ->assertDontSee('Nuevo rol')
        ->assertDontSee('Copiar y ajustar')
        ->assertNoJavaScriptErrors();
});
