<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantType;
use App\Modules\Tenancy\Models\Tenant;
use Spatie\Permission\Models\Role;

mutates(TenantType::class);

// What an account admits is said by permissions: a union holds only roles made of compensation permissions.

/** A role of the company's own with `$permissions`, written straight to the tables as an old row or another path could. */
function ownRole(Tenant $tenant, array $permissions): Role
{
    // A company names each of its roles once.
    $label = 'Propio '.(Role::query()->where('tenant_id', $tenant->id)->count() + 1);
    $role = app(CreateCompanyRole::class)->handle($tenant, $label, ['view-compensation'], null, User::factory()->create());

    return $role->syncPermissions($permissions);
}

it('admits any permission in a company and only those meant for a union in a union', function (): void {
    expect(TenantType::Company->admits(['manage-organization', 'view-compensation']))->toBeTrue()
        ->and(TenantType::Union->admits(['view-compensation', 'manage-compensation']))->toBeTrue()
        ->and(TenantType::Union->admits(['view-compensation', 'view-dashboard']))->toBeFalse()
        ->and(TenantType::Union->admits(['fly-to-moon']))->toBeFalse()
        ->and(TenantType::Union->admits([]))->toBeTrue();
});

it('lets a union hold a role of its own made of compensation permissions and refuses one with compliance permissions', function (): void {
    $union = Tenant::factory()->union()->create();

    expect(TenantType::Union->allows(ownRole($union, ['view-compensation', 'manage-compensation'])))->toBeTrue()
        ->and(TenantType::Union->allows(ownRole($union, ['view-compensation', 'manage-organization'])))->toBeFalse();
});

it('lets a company hold a role of its own with compliance permissions', function (): void {
    expect(TenantType::Company->allows(ownRole(Tenant::factory()->create(), ['manage-organization', 'view-trauma-screenings'])))->toBeTrue();
});

it('keeps deciding the templates by the roles each account hands out, whether given as a role or as its key', function (): void {
    $template = fn (TenantRole $role): Role => Role::query()->where('name', $role->value)->whereNull('tenant_id')->sole();

    expect(TenantType::Union->allows($template(TenantRole::Hr)))->toBeTrue()
        ->and(TenantType::Union->allows($template(TenantRole::Owner)))->toBeTrue()
        ->and(TenantType::Union->allows($template(TenantRole::Viewer)))->toBeFalse()
        ->and(TenantType::Union->allows(TenantRole::Committee))->toBeFalse()
        ->and(TenantType::Company->allows($template(TenantRole::Complaints)))->toBeTrue();
});
