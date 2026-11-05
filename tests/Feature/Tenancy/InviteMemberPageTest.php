<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantType;
use App\Modules\Tenancy\Http\Controllers\MemberController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\RoleMatrix;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

mutates(MemberController::class, RoleMatrix::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = ownerOf($this->tenant);
});

it('lives at /admin/{slug}/members/invitations/create', function (): void {
    expect(route('tenant.members.invitations.create', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/members/invitations/create');
});

it('offers the roles the owner can give and what each one can do (board V3-Roles)', function (): void {
    $this->actingAs($this->owner)
        ->get(route('tenant.members.invitations.create', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/invite-member')
            ->where('roles', [
                ['value' => 'hr', 'label' => 'Ayuda con las rutas'],
                ['value' => 'viewer', 'label' => 'Solo consulta'],
                ['value' => 'committee', 'label' => 'Comisión de seguridad e higiene'],
                ['value' => 'complaints', 'label' => 'Atención de quejas'],
            ])
            ->where('matrix.columns', ['Administra todo', 'Ayuda con las rutas', 'Solo consulta', 'Comisión de seguridad e higiene', 'Atención de quejas'])
            ->where('matrix.rows', fn ($rows): bool => collect($rows)->pluck('label')->all() === [
                'Ver rutas y expedientes',
                'Avanzar tareas y documentos',
                'Resultados NOM-035 agregados',
                'Canalización y datos de salud',
                'Avisos de accidentes',
                'Contenido de quejas y denuncias',
                'Canal de quejas: quién atiende y conteos',
                'Compensación: salarios y escenarios',
                'Usuarios y configuración',
            ])
            ->where('matrix.rows.3.cells', ['Sí', 'Sí', 'No', 'No', 'No'])
            ->where('matrix.rows.8.cells', ['Sí', 'No', 'No', 'No', 'No']));
});

it('shows a union only its roles and no matrix of the company tools', function (): void {
    $union = Tenant::factory()->create(['type' => TenantType::Union]);

    $this->actingAs(ownerOf($union))
        ->get(route('tenant.members.invitations.create', $union))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', [['value' => 'hr', 'label' => 'Ayuda con las rutas']])
            ->where('matrix', null));
});

it('is only for the owner', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))
        ->get(route('tenant.members.invitations.create', $this->tenant))
        ->assertForbidden();
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('says of each permission what the role really has', function (string $permission, int $row): void {
    $cells = RoleMatrix::ROWS[$row]['cells'];

    foreach (RoleMatrix::COLUMNS as $index => $role) {
        $granted = Role::findByName($role->value)->hasPermissionTo($permission);

        expect($cells[$index] !== 'No')->toBe($granted, "{$role->value} and {$permission}");
    }
})->with([
    'canalización' => ['view-trauma-screenings', 3],
    'avisos' => ['review-workplaces', 4],
    'contenido de quejas' => ['handle-complaints', 5],
    'canal de quejas' => ['manage-complaint-channel', 6],
    'compensación' => ['manage-compensation', 7],
    'usuarios' => ['manage-members', 8],
]);
