<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Listeners;

use App\Modules\Attendance\Actions\ReopenWeeks;
use App\Modules\Attendance\Events\OfflineMarkAccepted;

/**
 * An accepted mark made without a connection reopens the confirmed week it falls in (a disputed one stays disputed). The
 * delivery dispatches the event inside its transaction with the person's lock on their marks already taken, so the row lock
 * of ReopenWeeks runs in that same transaction: a confirmation in flight waits and then sees the mark. No transaction of its
 * own, and not queued.
 */
final readonly class ReopenOnOfflineMark
{
    public function __construct(private ReopenWeeks $reopen) {}

    public function handle(OfflineMarkAccepted $event): void
    {
        $this->reopen->forOfflineMark($event->markId);
    }
}
