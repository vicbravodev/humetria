<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Attendance\Events\OfflineMarkReviewed;
use App\Modules\Notifications\Support\PushNotifier;

/** RH reviewed a mark made without connection (verified, or incorrect with its correction): the person gets the same fixed notice. */
final readonly class NotifyMarkReviewed
{
    public function __construct(private PushNotifier $notifier) {}

    public function handle(OfflineMarkReviewed $event): void
    {
        $this->notifier->notify($event->employeeId);
    }
}
