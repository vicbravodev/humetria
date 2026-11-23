<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Events\MemberPermissionsChanged;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use App\Modules\Tenancy\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/** Sets the permissions a person has in the company besides those of their role. */
final readonly class GrantMemberPermissions
{
    public function __construct(private RecordAudit $recordAudit, private CompanyRoles $roles) {}

    /**
     * @param  list<string>  $permissions
     *
     * @throws InvalidArgumentException when a permission is not one the company can hand out.
     */
    public function handle(TenantMembership $member, array $permissions, User $by): void
    {
        $person = $member->user()->sole();
        $tenant = $member->tenant()->sole();
        $this->roles->assertAssignable($tenant, $permissions);

        DB::transaction(function () use ($member, $person, $tenant, $permissions, $by): void {
            $before = $this->roles->directPermissionsOf($person, $tenant);
            $added = array_values(array_diff($permissions, $before));

            $this->roles->syncDirectPermissions($person, $tenant, $permissions);
            event(new MemberPermissionsChanged($tenant->id, $person->id));

            Log::info('tenant_member.permissions_changed', ['tenant_id' => $tenant->id, 'user_id' => $by->id, 'member_user_id' => $person->id]);
            $this->recordAudit->handle('tenant_member.permissions_changed', $member, [
                'member_user_id' => $person->id,
                'added' => $added,
                'removed' => array_values(array_diff($before, $permissions)),
                'sensitive' => array_any($added, fn (string $name): bool => PermissionCatalog::find($name)?->sensitive === true),
            ]);
        });
    }
}
