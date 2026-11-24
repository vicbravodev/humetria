<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('opens on the templates, locked, each with what it lets do, before the company has a role of its own', function (bool $dark, bool $mobile): void {
    signInToPanel();

    visitPage('tenancy/roles', '/admin/acme/roles', $dark, $mobile)
        ->assertSee('Roles y permisos')
        ->assertSee('Roles de tu empresa')
        ->assertSee('Plantillas de Humetría')
        ->assertSee('Ayuda con las rutas')
        ->assertSee('Qué puede hacer')
        ->assertSee('Ve Panel. También atiende quejas asignadas.')
        ->assertSee('Copiar y ajustar')
        ->assertSee('Tienes 0 de 20 roles propios.')
        ->assertSee('Nuevo rol');
})->with($looks);

it('says in the company\'s tab how to make its first role', function (bool $dark, bool $mobile): void {
    signInToPanel();

    visitPage('tenancy/roles', '/admin/acme/roles?tab=propios', $dark, $mobile)
        ->assertSee('Aún no hay roles propios')
        ->assertDontSee('Copiar y ajustar');
})->with($looks);

it('lists the roles of the company with their people and pending invitations', function (bool $dark, bool $mobile): void {
    $owner = User::factory()->create();
    signInToPanel($owner);
    app(CreateCompanyRole::class)->handle(Tenant::query()->where('slug', 'acme')->sole(), 'Auditoría', ['view-dashboard', 'view-vacations'], TenantRole::Viewer, $owner);

    visitPage('tenancy/roles', '/admin/acme/roles', $dark, $mobile)
        ->assertSee('Auditoría')
        ->assertSee('Basado en Solo consulta')
        ->assertSee('Ve Panel y Vacaciones.')
        ->assertSee('Editar')
        ->assertSee('1 de 20 roles propios');
})->with($looks);

it('opens the editor of a new role', function (bool $dark, bool $mobile): void {
    signInToPanel();

    visitPage('tenancy/role-editor', '/admin/acme/roles/create', $dark, $mobile)
        ->assertSee('Nuevo rol')
        ->assertSee('Empezar desde')
        ->assertSee('Cumple la norma')
        ->assertSee('Vacaciones')
        ->assertSee('Sensible')
        ->assertSee('Quien tenga este rol no tiene acceso a ninguna área.')
        ->assertSee('Crear rol');
})->with($looks);

it('opens the editor of a role someone holds, saying so', function (bool $dark, bool $mobile): void {
    $owner = User::factory()->create();
    signInToPanel($owner);
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $role = app(CreateCompanyRole::class)->handle($tenant, 'Auditoría', ['view-dashboard', 'view-vacations'], TenantRole::Viewer, $owner);
    $person = User::factory()->create();
    App\Modules\Tenancy\Models\TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();
    resolve(App\Modules\Tenancy\Support\CompanyRoles::class)->assign($person, $tenant, $role);

    visitPage('tenancy/role-editor', "/admin/acme/roles/{$role->id}/edit", $dark, $mobile)
        ->assertSee('Auditoría')
        ->assertSee('Basado en Solo consulta.')
        ->assertDontSee('Una persona tiene este rol.')
        ->assertSee('Quien tenga este rol ve Panel y Vacaciones.')
        ->assertSee('Los cambios aplican a la persona con este rol en cuanto guardes.')
        ->assertSee('Guardar rol')
        ->assertSee('Borrar rol');
})->with($looks);

it('opens the permissions a person has besides their role', function (bool $dark, bool $mobile): void {
    signInToPanel();
    $member = App\Modules\Tenancy\Models\TenantMembership::factory()
        ->for(Tenant::query()->where('slug', 'acme')->sole())
        ->for(User::factory()->create())
        ->viewer()->create();

    visitPage('tenancy/member-permissions', "/admin/acme/members/{$member->id}/permissions", $dark, $mobile)
        ->assertSee('Permisos adicionales')
        ->assertSee('Su rol: Solo consulta')
        ->assertSee('· su rol')
        ->assertSee('Guardar permisos');
})->with($looks);
