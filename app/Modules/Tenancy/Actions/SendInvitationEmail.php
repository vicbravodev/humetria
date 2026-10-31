<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Notifications\InvitationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final readonly class SendInvitationEmail
{
    public function __construct(private Headcount $headcount) {}

    /**
     * Queues the email with the one-time link once the surrounding transaction commits: a worker must never find an
     * invitation that was rolled back, nor send a link to it. Who invited and the size of the company are read now.
     */
    public function handle(TenantInvitation $invitation, string $tradeName, string $token): void
    {
        $inviter = $invitation->invited_by === null ? null : User::query()->find($invitation->invited_by)?->name;
        $workCenters = WorkCenter::query()->withoutGlobalScopes()->where('tenant_id', $invitation->tenant_id)->whereNull('archived_at')->count();
        $people = $this->headcount->ofCompany($invitation->tenant_id);

        DB::afterCommit(fn () => Notification::route('mail', $invitation->email)->notify(
            new InvitationEmail($tradeName, $invitation->email, $token, $invitation->expires_at, $invitation->role, $inviter, $workCenters, $people),
        ));
    }
}
