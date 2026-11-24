<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Complaints\Listeners\EndAssignmentsOnRoleConflict;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Actions\UpdateCompanyRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

mutates(EndAssignmentsOnRoleConflict::class);

// Editing a role with many holders costs the same queries as with one: its people are told in a batch, not one by one.
function queriesToEditRoleWithHolders(int $holders): int
{
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $role = app(CreateCompanyRole::class)->handle($tenant, 'Auditoría', ['view-dashboard'], null, $owner);

    foreach (User::factory()->count($holders)->create() as $person) {
        DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => $person->getMorphClass(), 'model_id' => $person->id, 'tenant_id' => $tenant->id]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(UpdateCompanyRole::class)->handle($role, 'Auditoría', ['view-dashboard', 'manage-organization'], $owner);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

it('edits a role of five holders in as many queries as one of a single holder', function (): void {
    expect(queriesToEditRoleWithHolders(5))->toBe(queriesToEditRoleWithHolders(1));
});
