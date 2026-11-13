<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Exceptions;

use RuntimeException;

/**
 * A reminder already went out to the people of this evaluation less than the wait ago (`campaigns.resend_cooldown_hours`),
 * so no second one goes: `hoursLeft` is how long until another may.
 */
final class ReminderTooSoon extends RuntimeException
{
    public function __construct(public readonly int $hoursLeft)
    {
        parent::__construct("A reminder already went out; another can in {$hoursLeft} hours.");
    }
}
