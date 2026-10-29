<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Data\MemberInvitationData;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class InviteMember
{
    public function __construct(private RecordAudit $recordAudit, private SendInvitationEmail $sendEmail) {}

    /**
     * Invites someone into the company with a role and emails them the one-time link.
     */
    public function handle(Tenant $tenant, MemberInvitationData $data, User $invitedBy): TenantInvitation
    {
        return DB::transaction(function () use ($tenant, $data, $invitedBy): TenantInvitation {
            $token = Str::random(64);
            $invitation = $tenant->invitations()->create([
                'role' => $data->role,
                'name' => $data->name,
                'email' => $data->email,
                'token_hash' => TenantInvitation::hashToken($token),
                'expires_at' => now()->addDays(TenantInvitation::VALID_DAYS),
                'invited_by' => $invitedBy->id,
            ]);

            // Ids and the role only: the email, the name and the token never reach the log or the audit trail.
            Log::info('tenant_invitation.sent', [
                'tenant_id' => $tenant->id,
                'invitation_id' => $invitation->id,
                'user_id' => $invitedBy->id,
                'role' => $data->role->value,
            ]);
            $this->recordAudit->handle('tenant_invitation.sent', $invitation, ['tenant_id' => $tenant->id, 'role' => $data->role->value]);

            $this->sendEmail->handle($invitation, $tenant->trade_name, $token);

            return $invitation;
        });
    }
}
