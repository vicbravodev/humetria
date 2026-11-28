<?php

declare(strict_types=1);

namespace App\Modules\Operations\Support;

use App\Modules\Messaging\Data\ChannelTraffic;

/** The traffic of each channel as a page prop. */
final class TrafficRows
{
    /**
     * @param  list<ChannelTraffic>  $traffic
     * @return list<array{channel: string, total: int, delivered: int, read: int, rejected: int}>
     */
    public static function of(array $traffic): array
    {
        return array_map(
            fn (ChannelTraffic $row): array => ['channel' => $row->channel, 'total' => $row->total, 'delivered' => $row->delivered, 'read' => $row->read, 'rejected' => $row->rejected],
            $traffic,
        );
    }
}
