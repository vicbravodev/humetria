<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ChangeMemberRole
{
    public function __construct(private RecordAudit $recordAudit, private CompanyRoles $roles) {}

    public function handle(TenantMembership $member, TenantRole $role, User $by): void
    {
        $person = $member->user()->sole();
        $tenant = $member->tenant()->sole();
        $from = $this->roles->of($person, $tenant);

        if ($from === $role) {
            return;
        }

        DB::transaction(function () use ($member, $person, $tenant, $role, $from, $by): void {
            $this->roles->assign($person, $tenant, $role);

            $change = ['member_user_id' => $person->id, 'from' => $from?->value, 'to' => $role->value];
            Log::info('tenant_member.role_changed', ['tenant_id' => $tenant->id, 'user_id' => $by->id, ...$change]);
            $this->recordAudit->handle('tenant_member.role_changed', $member, $change);
        });
    }
}
