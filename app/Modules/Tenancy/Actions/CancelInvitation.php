<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class CancelInvitation
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Stops the link of an open invitation from working.
     */
    public function handle(TenantInvitation $invitation, User $by): void
    {
        DB::transaction(function () use ($invitation, $by): void {
            $invitation->update(['cancelled_at' => now()]);

            Log::info('tenant_invitation.cancelled', [
                'tenant_id' => $invitation->tenant_id,
                'invitation_id' => $invitation->id,
                'user_id' => $by->id,
            ]);
            $this->recordAudit->handle('tenant_invitation.cancelled', $invitation, ['tenant_id' => $invitation->tenant_id]);
        });
    }
}
