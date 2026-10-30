<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Notifications\InvitationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final readonly class SendInvitationEmail
{
    /**
     * Queues the email with the one-time link once the surrounding transaction commits: a worker must never find an
     * invitation that was rolled back, nor send a link to it.
     */
    public function handle(TenantInvitation $invitation, string $tradeName, string $token): void
    {
        DB::afterCommit(fn () => Notification::route('mail', $invitation->email)->notify(
            new InvitationEmail($tradeName, $invitation->name, $token, $invitation->expires_at, $invitation->role),
        ));
    }
}
