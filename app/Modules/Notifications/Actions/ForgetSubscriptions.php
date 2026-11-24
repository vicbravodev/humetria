<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\PushSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Deletes the subscriptions of a person of the current company: one address, or every one when none is given. `$reason` is
 * `unsubscribed` (the person, from their phone) or `revoked` (their access was taken away). It touches only the person's own rows, so
 * asking for another's address changes nothing and says nothing.
 */
final readonly class ForgetSubscriptions
{
    /** @return int how many it deleted */
    public function handle(string $employeeId, ?string $endpoint, string $reason): int
    {
        $query = PushSubscription::query()->where('employee_id', $employeeId);

        if ($endpoint !== null) {
            $query->where('endpoint', $endpoint);
        }

        // The base query keeps the company's scope and says how many rows it deleted.
        $count = $query->toBase()->delete();

        // Said only when something went: the access actions raise their event for everyone, most with no phone to forget.
        if ($count > 0) {
            Log::info('push.subscriptions_forgotten', ['count' => $count, 'reason' => $reason]);
        }

        return $count;
    }
}
