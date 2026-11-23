<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Exceptions\CompanyRoleLimitReached;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/** Creates a role of the company's own. Its internal name is never the typed label, so no label can collide with a template. */
final readonly class CreateCompanyRole
{
    public function __construct(private RecordAudit $recordAudit, private CompanyRoles $roles) {}

    /**
     * @param  list<string>  $permissions
     *
     * @throws CompanyRoleLimitReached
     * @throws InvalidArgumentException when a permission is not one the company can hand out.
     */
    public function handle(Tenant $tenant, string $label, array $permissions, ?TenantRole $basedOn, User $by): Role
    {
        $sensitive = $this->roles->assertAssignable($tenant, $permissions);

        return DB::transaction(function () use ($tenant, $label, $permissions, $basedOn, $by, $sensitive): Role {
            $tenant->newQuery()->whereKey($tenant->id)->lockForUpdate()->sole();
            $this->roles->customRoles($tenant)->count() < config()->integer('tenancy.max_custom_roles') || throw new CompanyRoleLimitReached;

            $role = Role::query()->create([
                'name' => 'custom_'.Str::uuid(),
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
                'label' => $label,
                'based_on' => $basedOn?->value,
            ]);
            $role->syncPermissions($permissions);

            Log::info('tenant_role.created', ['tenant_id' => $tenant->id, 'user_id' => $by->id, 'role_id' => $role->id]);
            $this->recordAudit->handle('tenant_role.created', $role, ['label' => $label, 'based_on' => $basedOn?->value, 'permissions' => $permissions, 'sensitive' => $sensitive]);

            return $role;
        });
    }
}
