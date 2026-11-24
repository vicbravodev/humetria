<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantType;
use App\Modules\Tenancy\Http\Controllers\MemberController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\PermissionCatalog;
use App\Modules\Tenancy\Support\PermissionDefinition;
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
            ->where('matrix.rows', fn ($rows): bool => collect($rows)->pluck('label')->all() === array_map(fn (PermissionDefinition $permission): string => $permission->label, PermissionCatalog::all())));
});

it('adds the company\'s own roles, and only the permissions of the tools it has', function (): void {
    $role = app(CreateCompanyRole::class)->handle($this->tenant, 'Auditoría', ['view-dashboard', 'view-climate'], null, $this->owner);
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    $this->actingAs($this->owner)
        ->get(route('tenant.members.invitations.create', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles.4', ['value' => $role->name, 'label' => 'Auditoría'])
            ->where('matrix.columns.5', 'Auditoría')
            ->where('matrix.rows', fn ($rows): bool => ! collect($rows)->pluck('label')->contains('Ver vacaciones')
                && collect($rows)->firstWhere('label', 'Ver clima')['cells'][5] === 'Sí'
                && collect($rows)->firstWhere('label', 'Ver asistencia')['cells'][5] === 'No'));
});

it('says of each permission what each role really has', function (): void {
    $own = app(CreateCompanyRole::class)->handle($this->tenant, 'Auditoría', ['view-dashboard', 'handle-complaints'], null, $this->owner);
    $matrix = RoleMatrix::for($this->tenant);
    $roles = [...array_map(fn (string $name): Role => Role::query()->where('name', $name)->whereNull('tenant_id')->sole(), ['owner', 'hr', 'viewer', 'committee', 'complaints']), $own];

    expect($matrix)->not->toBeNull()->and($matrix['rows'] ?? [])->toHaveCount(count(PermissionCatalog::all()));

    foreach (PermissionCatalog::all() as $index => $permission) {
        foreach ($roles as $column => $role) {
            expect($matrix['rows'][$index]['cells'][$column] ?? null)->toBe($role->hasPermissionTo($permission->name) ? 'Sí' : 'No', "{$role->name} and {$permission->name}");
        }
    }
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
