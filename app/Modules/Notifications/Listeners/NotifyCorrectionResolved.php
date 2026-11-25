<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Attendance\Events\CorrectionResolved;
use App\Modules\Notifications\Support\PushNotifier;

/**
 * RH answered a request to correct, approving or rejecting it: the person gets the notice. It does not listen to `MarkCorrected`,
 * which also comes from a correction RH made on its own (the person asked for nothing) and never from a rejection.
 */
final readonly class NotifyCorrectionResolved
{
    public function __construct(private PushNotifier $notifier) {}

    public function handle(CorrectionResolved $event): void
    {
        $this->notifier->notify($event->employeeId);
    }
}
