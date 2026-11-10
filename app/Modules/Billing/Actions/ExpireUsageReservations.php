<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\UsageReservation;

final readonly class ExpireUsageReservations
{
    public function __construct(private ReleaseUsage $release) {}

    /**
     * Gives back the holds nobody settled or released in time (a process that died mid-answer), without counting them as used.
     *
     * @return int how many it expired.
     */
    public function handle(): int
    {
        $expired = 0;

        foreach (UsageReservation::query()->where('state', UsageReservation::RESERVED)->where('expires_at', '<=', now())->get(['tenant_id', 'idempotency_key']) as $stale) {
            $expired += $this->release->handle($stale->tenant_id, $stale->idempotency_key, UsageReservation::EXPIRED) ? 1 : 0;
        }

        return $expired;
    }
}
