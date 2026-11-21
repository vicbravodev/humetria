<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Enums;

/**
 * Where a message is. The order of rule 8: created < sent < delayed < delivered < read; failed, bounced and complained end it.
 */
enum DeliveryStatus: string
{
    case Created = 'created';
    case Sent = 'sent';
    case Delayed = 'delayed';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Unknown = 'unknown';
    case Paused = 'paused';

    public function rank(): int
    {
        return match ($this) {
            self::Created, self::Unknown, self::Paused => 0,
            self::Sent => 1,
            self::Delayed => 2,
            self::Delivered => 3,
            self::Read => 4,
            self::Failed, self::Bounced, self::Complained => 5,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Failed, self::Bounced, self::Complained], true);
    }

    /** Delivered or read; any other status is undelivered. */
    public function isDelivered(): bool
    {
        return in_array($this, [self::Delivered, self::Read], true);
    }
}
