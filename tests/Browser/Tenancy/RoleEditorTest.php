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

it('creates a role with a sensitive level and extra only after the owner confirms them', function (): void {
    signInToPanel();

    visit('/admin/acme/roles/create')
        ->type('label', 'Canalización')
        ->click('@level-complaints-handle')
        ->click('@extra-view-trauma-screenings')
        ->assertSee('Este rol abre información sensible')
        ->assertSee('Ver tamizajes de la Guía I · Atender quejas')
        ->assertSee('Quien tenga este rol atiende quejas asignadas y ve los tamizajes de la Guía I.')
        ->press('Crear rol')
        ->assertSee('Confirma que quieres dar permisos que abren información sensible.')
        ->click('#acknowledge-sensitive')
        ->press('Crear rol')
        ->assertSee('Rol creado.')
        ->assertSee('Canalización')
        ->assertNoJavaScriptErrors();

    $role = Role::query()->where('label', 'Canalización')->sole();
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing(['handle-complaints', 'view-trauma-screenings']);
});

it('starts a new role from a template, with each area at the template\'s level', function (): void {
    signInToPanel();

    visit('/admin/acme/roles')
        ->click('[aria-label="Copiar y ajustar Solo consulta"]')
        ->assertSee('Nuevo rol')
        ->assertChecked('input[name="preset"][value="viewer"]')
        ->assertChecked('input[name="level-vacations"][value="view"]')
        ->assertChecked('input[name="level-nom035"][value="view"]')
        ->assertChecked('input[name="level-complaints"][value="none"]')
        ->assertNoJavaScriptErrors();
});

it('sets every area from the card chosen to start from, and back to nothing from scratch', function (): void {
    signInToPanel();

    visit('/admin/acme/roles/create')
        ->click('@preset-committee')
        ->assertChecked('input[name="level-complaints"][value="handle"]')
        ->assertChecked('input[name="level-telework"][value="view"]')
        ->assertChecked('#extra-review-workplaces')
        ->type('label', 'Comisión norte')
        ->click('@level-vacations-manage')
        ->click('#acknowledge-sensitive')
        ->press('Crear rol')
        ->assertSee('Rol creado.')
        ->assertSee('Basado en Comisión de seguridad e higiene')
        ->assertNoJavaScriptErrors();

    expect(Role::query()->where('label', 'Comisión norte')->sole()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing([...Role::findByName('committee')->permissions->pluck('name')->all(), 'view-vacations', 'manage-vacations']);

    visit('/admin/acme/roles/create')
        ->click('@preset-committee')
        ->click('@preset-blank')
        ->assertChecked('input[name="level-complaints"][value="none"]')
        ->assertSee('Quien tenga este rol no tiene acceso a ninguna área.');
});

it('shows a set saved before levels as it is, and keeps it when another area is changed', function (): void {
    $owner = User::factory()->create();
    signInToPanel($owner);
    $role = app(CreateCompanyRole::class)->handle(Tenant::query()->where('slug', 'acme')->sole(), 'Heredado', ['view-dashboard', 'manage-vacations'], null, $owner);

    visit("/admin/acme/roles/{$role->id}/edit")
        ->assertSee('Tiene una combinación propia: Administrar vacaciones. Elige un nivel para ordenarla.')
        ->assertNotChecked('input[name="level-vacations"][value="none"]')
        ->assertNotChecked('input[name="level-vacations"][value="manage"]')
        ->click('@level-assistant-use')
        ->press('Guardar rol')
        ->assertSee('Rol actualizado.')
        ->assertNoJavaScriptErrors();

    expect($role->fresh()?->permissions->pluck('name')->all())->toEqualCanonicalizing(['view-dashboard', 'manage-vacations', 'use-assistant']);
});

it('brings the first error into view on a phone when the save at the bottom is refused', function (): void {
    signInToPanel();

    visit('/admin/acme/roles/create')->on()->mobile()
        ->type('label', 'Canalización')
        ->click('@level-complaints-handle')
        ->click('@save-bar')
        ->assertSee('Confirma que quieres dar permisos que abren información sensible.')
        ->assertScript('() => document.activeElement?.id', 'acknowledge-sensitive')
        ->assertScript('() => { const box = document.getElementById("acknowledge-sensitive").getBoundingClientRect(); return box.top >= 0 && box.bottom <= window.innerHeight; }', true)
        ->assertNoJavaScriptErrors();
});

it('marks a chosen «Sin acceso» in a quiet fill, not in the accent of a level that gives access', function (): void {
    signInToPanel();

    visit('/admin/acme/roles/create')
        ->assertChecked('input[name="level-dashboard"][value="none"]')
        ->assertScript(<<<'JS'
            () => {
                const probe = document.createElement('div');
                probe.className = 'bg-primary';
                document.body.append(probe);
                const accent = getComputedStyle(probe).backgroundColor;
                probe.remove();

                return getComputedStyle(document.querySelector('[data-test="level-dashboard-none"]')).backgroundColor !== accent;
            }
            JS, true)
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

it('deletes from inside the editor without also saving the role the dialog is in front of', function (): void {
    $owner = User::factory()->create();
    signInToPanel($owner);
    $free = app(CreateCompanyRole::class)->handle(Tenant::query()->where('slug', 'acme')->sole(), 'Pasajero', ['view-dashboard'], null, $owner);

    visit("/admin/acme/roles/{$free->id}/edit")
        // Every request the page makes from here on, by its verb: one POST (the delete, spoofed), not a second one from the editor's form.
        ->assertScript(<<<'JS'
            () => {
                window.verbs = [];
                const open = XMLHttpRequest.prototype.open;
                XMLHttpRequest.prototype.open = function (method, ...rest) { window.verbs.push(method.toUpperCase()); return open.call(this, method, ...rest); };
                const original = window.fetch;
                window.fetch = (input, init) => { window.verbs.push((init?.method ?? 'GET').toUpperCase()); return original(input, init); };

                return true;
            }
            JS, true)
        ->press('Borrar rol')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Rol borrado.')
        ->assertScript('() => window.verbs.filter((verb) => verb !== "GET").join()', 'POST')
        ->assertNoJavaScriptErrors();

    expect(Role::query()->whereKey($free->id)->exists())->toBeFalse();
});

it('raises an area of a person past their role, with the role\'s level as a floor, and confirms a sensitive one', function (): void {
    signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $person = User::factory()->create(['name' => 'Vera Vista']);
    $member = TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();

    visit("/admin/acme/members/{$member->id}/permissions")
        ->assertSee('Permisos adicionales de Vera Vista')
        ->assertChecked('input[name="level-vacations"][value="view"]')
        ->assertAttribute('input[name="level-vacations"][value="none"]', 'disabled', '')
        ->assertSee('· su rol')
        ->click('@level-vacations-manage')
        ->assertSee('Adicional')
        ->assertSee('Además de lo que trae su rol, Vera Vista administra Vacaciones.')
        ->click('@level-complaints-handle')
        ->assertSee('Estás abriendo información sensible')
        ->press('Guardar permisos')
        ->assertSee('Confirma que quieres dar permisos que abren información sensible.')
        ->click('#acknowledge-sensitive')
        ->press('Guardar permisos')
        ->assertSee('Permisos actualizados.')
        ->assertNoJavaScriptErrors();

    expect(resolve(CompanyRoles::class)->directPermissionsOf($person, $tenant))->toEqualCanonicalizing(['manage-vacations', 'handle-complaints']);
});

it('shows an area the person\'s role holds as a mix no level names as locked, with nothing to raise', function (): void {
    $owner = User::factory()->create();
    signInToPanel($owner);
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $role = app(CreateCompanyRole::class)->handle($tenant, 'Heredado', ['view-dashboard', 'manage-vacations'], null, $owner);
    $person = User::factory()->create(['name' => 'Hilda Heredada']);
    $member = TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();
    resolve(CompanyRoles::class)->assign($person, $tenant, $role);

    visit("/admin/acme/members/{$member->id}/permissions")
        ->assertSee('Combinación del rol')
        ->assertAttribute('input[name="level-vacations"][value="view"]', 'disabled', '')
        ->assertAttribute('input[name="level-vacations"][value="manage"]', 'disabled', '')
        ->assertAttribute('input[name="level-vacations"][value="none"]', 'disabled', '')
        ->assertNotChecked('input[name="level-vacations"][value="none"]')
        ->assertNoJavaScriptErrors();
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
