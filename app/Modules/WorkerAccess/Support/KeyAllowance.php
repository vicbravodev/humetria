<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Models\WorkerCredential;

/**
 * Whether a session may register a key (decision 3 of the spec): one opened with a link RH issued (the first key, or the
 * one after a reissue) or with a key the person has (another one), while the person has fewer keys in use than allowed.
 * A session opened with a link the person asked for never may.
 */
final class KeyAllowance
{
    /** Why the session may not register a key: `forbidden` (how it entered) or `full` (keys in use); null when it may. */
    public static function refusal(OpenSession $session): ?string
    {
        if ($session->via === EntryVia::RequestedLink) {
            return 'forbidden';
        }

        return self::full($session->identity->employeeId) ? 'full' : null;
    }

    /** Whether the person has a key in use: then a recent proof is their key's signature, never a code. */
    public static function hasKey(string $employeeId): bool
    {
        return WorkerCredential::query()->where('employee_id', $employeeId)->whereNull('revoked_at')->exists();
    }

    public static function full(string $employeeId): bool
    {
        return WorkerCredential::query()->where('employee_id', $employeeId)->whereNull('revoked_at')->count() >= config()->integer('worker_access.max_keys');
    }
}
