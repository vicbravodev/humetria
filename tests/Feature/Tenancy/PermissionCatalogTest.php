<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;

/** @return list<string> */
function catalogNames(): array
{
    return array_map(fn ($permission): string => $permission->name, PermissionCatalog::all());
}

it('has every permission row in the catalog and every catalog entry as a row', function (): void {
    $rows = DB::table('permissions')->pluck('name')->all();

    expect(catalogNames())->toEqualCanonicalizing($rows)
        ->and(catalogNames())->toHaveCount(count(array_unique(catalogNames())));
});

it('finds a permission by name and returns null for an unknown one', function (): void {
    expect(PermissionCatalog::find('view-vacations')?->module)->toBe('vacations')
        ->and(PermissionCatalog::find('nope'))->toBeNull();
});

it('marks manage-members as owner only and leaves it out of assignable()', function (): void {
    $names = array_map(fn ($permission): string => $permission->name, PermissionCatalog::assignable(Tenant::factory()->create()));

    expect(PermissionCatalog::find('manage-members')?->ownerOnly)->toBeTrue()
        ->and($names)->not->toContain('manage-members')
        ->and($names)->toContain('manage-organization', 'view-dashboard');
});

it('gives a union only the compensation permissions', function (): void {
    $names = array_map(fn ($permission): string => $permission->name, PermissionCatalog::assignable(Tenant::factory()->union()->create()));

    expect($names)->toEqualCanonicalizing(['view-compensation', 'manage-compensation']);
});

it('has exactly these sensitive permissions', function (): void {
    $sensitive = array_values(array_map(
        fn ($permission): string => $permission->name,
        array_filter(PermissionCatalog::all(), fn ($permission): bool => $permission->sensitive),
    ));

    expect($sensitive)->toEqualCanonicalizing([
        'view-trauma-screenings', 'handle-complaints', 'manage-complaint-channel',
        'view-compensation', 'manage-compensation', 'review-workplaces',
    ]);
});

it('says why a permission is unavailable when its module is not contracted', function (): void {
    $tenant = Tenant::factory()->create();
    TenantModule::factory()->for($tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);
    $vacations = PermissionCatalog::find('view-vacations') ?? throw new RuntimeException('missing');
    $dashboard = PermissionCatalog::find('view-dashboard') ?? throw new RuntimeException('missing');

    expect(PermissionCatalog::unavailableReason($tenant, $vacations))->toBe('Tu plan no incluye '.Module::Vacations->label().'.')
        ->and(PermissionCatalog::unavailableReason($tenant, $dashboard))->toBeNull();
});

it('hands the three coverage permissions to the roles that see those pages today', function (): void {
    $roles = fn (string $permission): array => DB::table('roles')
        ->join('role_has_permissions', 'roles.id', '=', 'role_has_permissions.role_id')
        ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
        ->where('permissions.name', $permission)->whereNull('roles.tenant_id')->pluck('roles.name')->all();

    expect($roles('view-dashboard'))->toEqualCanonicalizing(['owner', 'hr', 'viewer', 'committee', 'complaints'])
        ->and($roles('view-work-centers'))->toEqualCanonicalizing(['owner', 'hr', 'viewer'])
        ->and($roles('view-nom035-route'))->toEqualCanonicalizing(['owner', 'hr', 'viewer']);
});

it('adds label and based_on to roles', function (): void {
    expect(Schema::hasColumns('roles', ['label', 'based_on']))->toBeTrue();
});
