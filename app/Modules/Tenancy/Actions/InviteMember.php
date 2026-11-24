<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Data\MemberInvitationData;
use App\Modules\Tenancy\Exceptions\RoleNotAllowedInAccount;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class InviteMember
{
    public function __construct(private RecordAudit $recordAudit, private SendInvitationEmail $sendEmail, private CompanyRoles $roles) {}

    /**
     * Invites someone into the company with a role and, besides it, any permissions, and emails them the one-time link.
     *
     * @throws RoleNotAllowedInAccount when the role is the owner's, another company's, or holds what the account cannot have.
     * @throws InvalidArgumentException when an extra permission is not one the company can hand out.
     */
    public function handle(Tenant $tenant, MemberInvitationData $data, User $invitedBy): TenantInvitation
    {
        // The source of truth for what an account hands out: a form that skipped its own check still stops here.
        $role = $this->roles->assignable($tenant, $data->role);
        ($role !== null && $tenant->type->allows($role)) || throw new RoleNotAllowedInAccount;
        $sensitive = $this->roles->assertAssignable($tenant, $data->permissions);

        return DB::transaction(function () use ($tenant, $data, $invitedBy, $role, $sensitive): TenantInvitation {
            $token = Str::random(64);
            $invitation = $tenant->invitations()->create([
                'role_id' => $role->id,
                'extra_permissions' => $data->permissions,
                'name' => $data->name,
                'email' => $data->email,
                'token_hash' => TenantInvitation::hashToken($token),
                'expires_at' => now()->addDays(TenantInvitation::VALID_DAYS),
                'invited_by' => $invitedBy->id,
            ]);

            // Ids and the role's internal name only: the email, the name, the token and the typed label never reach the log
            // or the audit trail.
            Log::info('tenant_invitation.sent', [
                'tenant_id' => $tenant->id,
                'invitation_id' => $invitation->id,
                'user_id' => $invitedBy->id,
                'role' => $role->name,
            ]);
            $extras = $data->permissions === [] ? [] : ['permissions' => $data->permissions, 'sensitive' => $sensitive];
            $this->recordAudit->handle('tenant_invitation.sent', $invitation, ['tenant_id' => $tenant->id, 'role' => $role->name, ...$extras]);

            $this->sendEmail->handle($invitation, $tenant->trade_name, $token);

            return $invitation;
        });
    }
}
