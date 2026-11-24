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

it('lists the templates, locked, and says how to make the first role of the company', function (bool $dark, bool $mobile): void {
    signInToPanel();

    visitPage('tenancy/roles', '/admin/acme/roles', $dark, $mobile)
        ->assertSee('Roles y permisos')
        ->assertSee('Plantillas de Humetría')
        ->assertSee('Ayuda con las rutas')
        ->assertSee('Copiar y ajustar')
        ->assertSee('Aún no hay roles propios')
        ->assertSee('Nuevo rol');
})->with($looks);

it('lists the roles of the company with their people and pending invitations', function (bool $dark, bool $mobile): void {
    $owner = User::factory()->create();
    signInToPanel($owner);
    app(CreateCompanyRole::class)->handle(Tenant::query()->where('slug', 'acme')->sole(), 'Auditoría', ['view-dashboard', 'view-vacations'], TenantRole::Viewer, $owner);

    visitPage('tenancy/roles', '/admin/acme/roles', $dark, $mobile)
        ->assertSee('Auditoría')
        ->assertSee('Basado en Solo consulta')
        ->assertSee('Propio')
        ->assertSee('Editar')
        ->assertSee('1 de 20 roles propios');
})->with($looks);

it('opens the editor of a new role', function (bool $dark, bool $mobile): void {
    signInToPanel();

    visitPage('tenancy/role-editor', '/admin/acme/roles/create', $dark, $mobile)
        ->assertSee('Nuevo rol')
        ->assertSee('Empezar desde')
        ->assertSee('Qué puede hacer')
        ->assertSee('Ver vacaciones')
        ->assertSee('Sensible')
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
        ->assertSee('Una persona tiene este rol.')
        ->assertSee('1 de 2')
        ->assertSee('Guardar cambios')
        ->assertSee('Borrar rol');
})->with($looks);

it('opens the permissions a person has besides their role', function (bool $dark, bool $mobile): void {
    signInToPanel();
    $member = App\Modules\Tenancy\Models\TenantMembership::factory()
        ->for(App\Modules\Tenancy\Models\Tenant::query()->where('slug', 'acme')->sole())
        ->for(App\Models\User::factory()->create())
        ->viewer()->create();

    visitPage('tenancy/member-permissions', "/admin/acme/members/{$member->id}/permissions", $dark, $mobile)
        ->assertSee('Permisos adicionales')
        ->assertSee('Su rol: Solo consulta')
        ->assertSee('Viene de su rol')
        ->assertSee('Guardar permisos');
})->with($looks);
