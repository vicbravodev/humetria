<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('stores the hr and viewer roles next to the owner', function (): void {
    $tenant = Tenant::factory()->create();
    TenantMembership::factory()->for($tenant)->create();
    TenantMembership::factory()->for($tenant)->hr()->count(2)->create();
    TenantMembership::factory()->for($tenant)->viewer()->count(2)->create();

    expect(TenantMembership::query()->where('tenant_id', $tenant->id)->pluck('role')->map(fn (TenantRole $role): string => $role->value)->sort()->values()->all())
        ->toBe(['hr', 'hr', 'owner', 'viewer', 'viewer']);
});

it('rejects an unknown role in a membership or an invitation', function (string $table): void {
    $tenant = Tenant::factory()->create();
    $row = $table === 'tenant_memberships'
        ? ['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'role' => 'admin']
        : [...TenantInvitation::factory()->raw(['tenant_id' => $tenant->id, 'role' => 'admin']), 'id' => fake()->uuid()];

    expect(fn () => DB::table($table)->insert($row))->toThrow(QueryException::class, 'role_known');
})->with(['tenant_memberships', 'tenant_invitations']);

it('lets a company have several open member invitations, one per email', function (): void {
    $tenant = Tenant::factory()->create();
    TenantInvitation::factory()->for($tenant)->create();
    TenantInvitation::factory()->for($tenant)->role(TenantRole::Hr)->create(['email' => 'ana@acme.mx']);
    TenantInvitation::factory()->for($tenant)->role(TenantRole::Viewer)->create(['email' => 'luis@acme.mx']);

    expect(fn () => TenantInvitation::factory()->for($tenant)->role(TenantRole::Viewer)->create(['email' => 'ana@acme.mx']))
        ->toThrow(UniqueConstraintViolationException::class, 'tenant_invitations_one_open_per_email');
});

it('still allows a single open owner invitation per company', function (): void {
    $tenant = Tenant::factory()->create();
    TenantInvitation::factory()->for($tenant)->create();

    expect(fn () => TenantInvitation::factory()->for($tenant)->create())
        ->toThrow(UniqueConstraintViolationException::class, 'tenant_invitations_one_open_owner');
});
