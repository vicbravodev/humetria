<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;

/**
 * Postgres changes its tables inside a transaction too: the rollback runs here and the test's own transaction takes it
 * back, so the shared test database keeps the schema it has.
 */
it('rolls back to a role column without deleting any invitation', function (): void {
    $tenant = Tenant::factory()->create();
    $by = ownerOf($tenant);
    $role = fn (string $label, ?TenantRole $basedOn) => app(CreateCompanyRole::class)->handle($tenant, $label, ['view-dashboard'], $basedOn, $by);

    $fromHr = TenantInvitation::factory()->for($tenant)->role($role('Auditoría', TenantRole::Hr))->create();
    $fromNothing = TenantInvitation::factory()->for($tenant)->role($role('Libre', null))->create();
    $template = TenantInvitation::factory()->for($tenant)->role(TenantRole::Committee)->create();
    $owner = TenantInvitation::factory()->for($tenant)->create();
    $deleted = TenantInvitation::factory()->for($tenant)->role($gone = $role('Pasado', TenantRole::Hr))->cancelled()->create();
    $gone->delete();

    (require database_path('migrations/2027_01_28_100000_point_invitations_at_roles.php'))->down();

    $roles = DB::table('tenant_invitations')->pluck('role', 'id')->all();

    expect($roles)->toEqual([
        $fromHr->id => 'hr',
        $fromNothing->id => 'viewer',
        $template->id => 'committee',
        $owner->id => 'owner',
        $deleted->id => 'viewer',
    ]);
});
