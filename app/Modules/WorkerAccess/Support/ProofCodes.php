<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The six-digit code a person without a key proves with (decision 2 of the spec). Only its hash is kept, in the cache, tied
 * to the person and to the session it was asked in, for 10 minutes (config/worker_access.php); it takes 5 tries, the sixth
 * is refused even when right, and it is good once. A new code voids the one before and is never the same as it. The code
 * never reaches a log, a page or the database.
 */
final readonly class ProofCodes
{
    public function issue(string $employeeId, string $sessionId): string
    {
        $previous = Cache::get(self::key($employeeId));

        do {
            $code = sprintf('%06d', random_int(0, 999_999));
        } while ($previous === self::hash($code, $sessionId));

        $expires = now()->addMinutes(config()->integer('worker_access.proof.code_minutes'));
        Cache::put(self::key($employeeId), self::hash($code, $sessionId), $expires);
        Cache::put(self::key($employeeId).':tries', 0, $expires);

        return $code;
    }

    /** @return 'passed'|'wrong'|'expired'|'locked' */
    public function check(string $employeeId, string $sessionId, string $code): string
    {
        $kept = Cache::get(self::key($employeeId));

        if (! is_string($kept)) {
            return 'expired';
        }

        // Counted before comparing, at once: two tries sent together never both go uncounted.
        $tries = Cache::increment(self::key($employeeId).':tries');

        if (! is_int($tries) || $tries > config()->integer('worker_access.proof.code_attempts')) {
            return 'locked';
        }

        if (! hash_equals($kept, self::hash($code, $sessionId))) {
            return 'wrong';
        }

        Cache::forget(self::key($employeeId));
        Cache::forget(self::key($employeeId).':tries');

        return 'passed';
    }

    private static function key(string $employeeId): string
    {
        return "worker-proof-code:{$employeeId}";
    }

    /** Keyed with the app's key: the six digits cannot be tried against a copy of the cache. */
    private static function hash(string $code, string $sessionId): string
    {
        return hash_hmac('sha256', "{$sessionId}|{$code}", config()->string('app.key'));
    }
}
