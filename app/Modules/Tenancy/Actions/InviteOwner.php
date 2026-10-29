<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Data\OwnerInvitationData;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class InviteOwner
{
    public function __construct(private RecordAudit $recordAudit, private SendInvitationEmail $sendEmail) {}

    /**
     * Invites someone to own the company, replacing the invitation that was open, and emails them the one-time link.
     */
    public function handle(Tenant $tenant, OwnerInvitationData $data, User $invitedBy): TenantInvitation
    {
        return DB::transaction(function () use ($tenant, $data, $invitedBy): TenantInvitation {
            // At most one is open (a unique index): its id stays on record so the replacement leaves a trace.
            $replacedId = $tenant->invitations()->open()->value('id');
            $tenant->invitations()->open()->update(['cancelled_at' => now()]);
            $replaced = is_string($replacedId) ? ['replaced_invitation_id' => $replacedId] : [];

            $token = Str::random(64);
            $invitation = $tenant->invitations()->create([
                'role' => TenantRole::Owner,
                'name' => $data->name,
                'email' => $data->email,
                'token_hash' => TenantInvitation::hashToken($token),
                'expires_at' => now()->addDays(TenantInvitation::VALID_DAYS),
                'invited_by' => $invitedBy->id,
            ]);

            // Ids only: the email, the name and the token never reach the log or the audit trail.
            Log::info('tenant_invitation.sent', [
                'tenant_id' => $tenant->id,
                'invitation_id' => $invitation->id,
                'user_id' => $invitedBy->id,
                ...$replaced,
            ]);
            $this->recordAudit->handle('tenant_invitation.sent', $invitation, ['tenant_id' => $tenant->id, ...$replaced]);

            $this->sendEmail->handle($invitation, $tenant->trade_name, $token);

            return $invitation;
        });
    }
}
