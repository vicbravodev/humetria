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

    /**
     * What counts as not delivered on a page (rule 10, «sin entregar»): the messages that ended badly. What is still on its way
     * (created, sent, delayed, unknown) is not a failure: right after a launch nothing has arrived yet and nothing has failed.
     *
     * @return list<string>
     */
    public static function undelivered(): array
    {
        return [self::Failed->value, self::Bounced->value, self::Complained->value, self::Paused->value];
    }

    /** Delivered or read; any other status is undelivered. */
    public function isDelivered(): bool
    {
        return in_array($this, [self::Delivered, self::Read], true);
    }
}
