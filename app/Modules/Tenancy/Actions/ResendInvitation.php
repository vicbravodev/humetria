<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Exceptions\InvitationNoLongerValid;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class ResendInvitation
{
    public function __construct(private RecordAudit $recordAudit, private SendOwnerInvitationEmail $sendEmail) {}

    /**
     * Replaces the link of an open invitation with a new one, valid for another {@see TenantInvitation::VALID_DAYS} days.
     *
     * @throws InvitationNoLongerValid when it was accepted or cancelled before it could be locked.
     */
    public function handle(TenantInvitation $invitation, User $by): TenantInvitation
    {
        return DB::transaction(function () use ($invitation, $by): TenantInvitation {
            $invitation = TenantInvitation::lockOpen($invitation);
            $token = Str::random(64);
            $invitation->update([
                'token_hash' => TenantInvitation::hashToken($token),
                'expires_at' => now()->addDays(TenantInvitation::VALID_DAYS),
                'invited_by' => $by->id,
            ]);

            // Ids only: the email, the name and the token never reach the log or the audit trail.
            Log::info('tenant_invitation.resent', [
                'tenant_id' => $invitation->tenant_id,
                'invitation_id' => $invitation->id,
                'user_id' => $by->id,
            ]);
            $this->recordAudit->handle('tenant_invitation.resent', $invitation, ['tenant_id' => $invitation->tenant_id]);

            $this->sendEmail->handle($invitation, $invitation->tenant()->sole()->trade_name, $token);

            return $invitation;
        });
    }
}
