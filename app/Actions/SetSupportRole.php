<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gives or takes away the platform's support role, from the command line and from the console, so both leave the same
 * log and audit entry. It returns whether anything changed: asking twice records once. A superadmin is never support;
 * whoever calls this checks that first.
 */
final readonly class SetSupportRole
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function grant(User $user): bool
    {
        if ($user->isSupport()) {
            return false;
        }

        DB::transaction(function () use ($user): void {
            // The platform role belongs to no company.
            setPermissionsTeamId(null);
            $user->assignRole(User::SUPPORT);

            Log::info('user.support_granted', ['user_id' => $user->id]);
            $this->recordAudit->handle('user.support_granted', $user);
        });

        return true;
    }

    public function revoke(User $user): bool
    {
        if (! $user->isSupport()) {
            return false;
        }

        DB::transaction(function () use ($user): void {
            setPermissionsTeamId(null);
            $user->removeRole(User::SUPPORT);

            Log::info('user.support_revoked', ['user_id' => $user->id]);
            $this->recordAudit->handle('user.support_revoked', $user);
        });

        return true;
    }
}
