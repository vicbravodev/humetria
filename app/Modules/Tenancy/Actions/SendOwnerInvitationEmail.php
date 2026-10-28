<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Notifications\OwnerInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final readonly class SendOwnerInvitationEmail
{
    /**
     * Emails the one-time link once the surrounding transaction commits: the notification is not queued, so it must
     * never go out for a row that ends up rolled back.
     */
    public function handle(TenantInvitation $invitation, string $tradeName, string $token): void
    {
        DB::afterCommit(fn () => Notification::route('mail', $invitation->email)->notify(
            new OwnerInvitation($tradeName, $invitation->name, $token, $invitation->expires_at),
        ));
    }
}
