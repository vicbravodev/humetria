<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Events\MemberPermissionsChanged;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\CompanyRoles;
use App\Modules\Tenancy\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final readonly class UpdateCompanyRole
{
    public function __construct(private RecordAudit $recordAudit, private CompanyRoles $roles) {}

    /**
     * @param  list<string>  $permissions
     *
     * @throws InvalidArgumentException when a permission is not one the company can hand out.
     */
    public function handle(Role $role, string $label, array $permissions, User $by): void
    {
        $this->roles->assertCustom($role);
        $tenant = Tenant::query()->whereKey($role->getAttribute('tenant_id'))->sole();
        $this->roles->assertAssignable($tenant, $permissions);

        DB::transaction(function () use ($role, $tenant, $label, $permissions, $by): void {
            $before = $role->permissions->map(fn (Permission $permission): string => $permission->name)->values()->all();
            $added = array_values(array_diff($permissions, $before));
            $removed = array_values(array_diff($before, $permissions));

            $role->update(['label' => $label]);
            $role->syncPermissions($permissions);

            foreach ($this->roles->holders($role) as $holder) {
                event(new MemberPermissionsChanged($tenant->id, $holder));
            }

            Log::info('tenant_role.updated', ['tenant_id' => $tenant->id, 'user_id' => $by->id, 'role_id' => $role->id]);
            $this->recordAudit->handle('tenant_role.updated', $role, [
                'label' => $label,
                'added' => $added,
                'removed' => $removed,
                'holders' => $this->roles->holders($role),
                'sensitive' => array_any($added, fn (string $name): bool => PermissionCatalog::find($name)?->sensitive === true),
            ]);
        });
    }
}
