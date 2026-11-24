<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Events\MemberRoleChanged;
use App\Modules\Tenancy\Exceptions\RoleNotAllowedInAccount;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/** Gives a person another role in the company. The permissions they have besides their role stay. */
final readonly class ChangeMemberRole
{
    public function __construct(private RecordAudit $recordAudit, private CompanyRoles $roles) {}

    /**
     * @param  TenantRole|Role  $role  a template, or a role of the person's company.
     *
     * @throws RoleNotAllowedInAccount when the role is the owner's, another company's, or holds what the account cannot have.
     */
    public function handle(TenantMembership $member, TenantRole|Role $role, User $by): void
    {
        $person = $member->user()->sole();
        $tenant = $member->tenant()->sole();
        $role = $role instanceof TenantRole ? $this->roles->template($role) : $role;
        $ownedBy = $role->getAttribute('tenant_id');
        // The owner changes only through its own invitation, never by changing someone's role.
        (($ownedBy === null || $ownedBy === $tenant->id) && $role->name !== TenantRole::Owner->value && $tenant->type->allows($role)) || throw new RoleNotAllowedInAccount;
        $from = $this->roles->roleOf($person, $tenant);

        if ($from?->is($role) === true) {
            return;
        }

        DB::transaction(function () use ($member, $person, $tenant, $role, $from, $by): void {
            $this->roles->assign($person, $tenant, $role);
            event(new MemberRoleChanged($tenant->id, $person->id, $role->name));

            $change = ['member_user_id' => $person->id, 'from' => $from?->name, 'to' => $role->name];
            Log::info('tenant_member.role_changed', ['tenant_id' => $tenant->id, 'user_id' => $by->id, ...$change]);
            $this->recordAudit->handle('tenant_member.role_changed', $member, $change);
        });
    }
}
