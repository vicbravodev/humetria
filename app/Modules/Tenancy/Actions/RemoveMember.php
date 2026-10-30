<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class RemoveMember
{
    public function __construct(private RecordAudit $recordAudit, private CompanyRoles $roles) {}

    /**
     * Takes away the person's access to the company: their membership and their role there. Their account, and their
     * access to other companies, stay.
     */
    public function handle(TenantMembership $member, User $by): void
    {
        $person = $member->user()->sole();
        $tenant = $member->tenant()->sole();

        DB::transaction(function () use ($member, $person, $tenant, $by): void {
            $removed = ['member_user_id' => $person->id, 'role' => $this->roles->of($person, $tenant)?->value];

            // Recorded before the row goes, so the entry can still name it as its subject.
            Log::info('tenant_member.removed', ['tenant_id' => $tenant->id, 'user_id' => $by->id, ...$removed]);
            $this->recordAudit->handle('tenant_member.removed', $member, $removed);

            $this->roles->remove($person, $tenant);
            $member->delete();
        });
    }
}
