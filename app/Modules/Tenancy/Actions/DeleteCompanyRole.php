<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Exceptions\CompanyRoleInUse;
use App\Modules\Tenancy\Exceptions\NotACompanyRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

final readonly class DeleteCompanyRole
{
    public function __construct(private RecordAudit $recordAudit, private CompanyRoles $roles) {}

    /**
     * @throws NotACompanyRole for a template.
     * @throws CompanyRoleInUse when a person holds the role or an open invitation points at it.
     */
    public function handle(Role $role, User $by): void
    {
        $this->roles->assertCustom($role);

        DB::transaction(function () use ($role, $by): void {
            // Locked company first, then the role (the order the other actions take): a person or invitation that points at
            // the role at the same time waits (their foreign key needs the row) and then finds it gone, or is seen here.
            Tenant::query()->whereKey($role->getAttribute('tenant_id'))->lockForUpdate()->sole();
            Role::query()->whereKey($role->id)->lockForUpdate()->sole();

            $this->roles->inUse($role) && throw new CompanyRoleInUse;

            $role->delete();

            Log::info('tenant_role.deleted', ['tenant_id' => $role->getAttribute('tenant_id'), 'user_id' => $by->id, 'role_id' => $role->id]);
            $this->recordAudit->handle('tenant_role.deleted', $role, ['label' => $role->getAttribute('label')]);
        });
    }
}
