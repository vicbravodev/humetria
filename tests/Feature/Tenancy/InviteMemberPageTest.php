<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantType;
use App\Modules\Tenancy\Http\Controllers\MemberController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantModule;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

mutates(MemberController::class);

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
            // Each with what it switches on: the floor of «Permisos adicionales».
            ->where('roles', fn ($roles): bool => collect($roles)->map(fn ($row): array => [$row['value'], $row['label']])->all() === [
                ['hr', 'Ayuda con las rutas'],
                ['viewer', 'Solo consulta'],
                ['committee', 'Comisión de seguridad e higiene'],
                ['complaints', 'Atención de quejas'],
            ] && collect($roles)->every(fn ($row): bool => $row['permissions'] === Role::findByName($row['value'])->permissions->pluck('name')->sort()->values()->all()))
        );
});

it('adds the company\'s own roles, and only the permissions of the tools it has', function (): void {
    $role = app(CreateCompanyRole::class)->handle($this->tenant, 'Auditoría', ['view-dashboard', 'view-climate'], null, $this->owner);
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    $this->actingAs($this->owner)
        ->get(route('tenant.members.invitations.create', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles.4', ['value' => $role->name, 'label' => 'Auditoría', 'permissions' => ['view-climate', 'view-dashboard']])
            ->where('groups', fn ($groups): bool => collect($groups)->flatMap(fn (array $group): array => $group['permissions'])->firstWhere('name', 'view-vacations')['unavailableReason'] === 'Tu plan no incluye Vacaciones.'));
});

it('shows a union only its roles', function (): void {
    $union = Tenant::factory()->create(['type' => TenantType::Union]);

    $this->actingAs(ownerOf($union))
        ->get(route('tenant.members.invitations.create', $union))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', [['value' => 'hr', 'label' => 'Ayuda con las rutas', 'permissions' => Role::findByName('hr')->permissions->pluck('name')->sort()->values()->all()]]));
});

it('is only for the owner', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))
        ->get(route('tenant.members.invitations.create', $this->tenant))
        ->assertForbidden();
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('reads the page in the same number of queries however many roles of its own the company has', function (int $roles): void {
    foreach (range(1, $roles) as $index) {
        app(CreateCompanyRole::class)->handle($this->tenant, "Rol {$index}", ['view-dashboard', 'view-climate'], null, $this->owner);
    }

    $this->actingAs($this->owner->fresh());
    $this->expectsDatabaseQueryCount(QUERIES_OF_THE_INVITE_PAGE);

    $this->get(route('tenant.members.invitations.create', $this->tenant))->assertOk();
})->with([1, 4]);

// Measured, then fixed so that a query per role or per permission breaks the test.
const QUERIES_OF_THE_INVITE_PAGE = 14;
