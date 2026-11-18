<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Contracts\DeliverySummary;
use App\Modules\Messaging\Data\ChannelDeliveryTally;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Tenancy\Contracts\CompanyChannels;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Data\ChannelSetting;

final readonly class ChannelDeliveryTallies implements DeliverySummary
{
    private const array ORDER = ['mail', 'whatsapp'];

    /**
     * What counts as not delivered: the messages that ended badly. What is still on its way (created, sent, delayed, unknown) is not
     * a failure: right after a launch nothing has arrived yet and nothing has failed.
     */
    private const array FAILED = ['failed', 'bounced', 'complained', 'paused'];

    public function __construct(private CurrentTenant $tenant, private CompanyChannels $channels) {}

    public function forSource(string $sourceType, string $sourceId): array
    {
        $tenantId = $this->tenant->id();
        $active = array_values(array_map(
            fn (ChannelSetting $setting): string => $setting->channel,
            array_filter($this->channels->settings($tenantId), fn (ChannelSetting $setting): bool => $setting->enabled),
        ));

        // One grouped query: the counts are all that leave it.
        $rows = Delivery::query()->toBase()
            ->where('tenant_id', $tenantId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereIn('channel', $active)
            ->groupBy('channel')
            ->selectRaw(
                'channel, count(*) as total, count(*) filter (where status in (?, ?)) as delivered, count(*) filter (where status in (?, ?, ?, ?)) as undelivered',
                [DeliveryStatus::Delivered->value, DeliveryStatus::Read->value, ...self::FAILED],
            )
            ->get()
            ->keyBy('channel');

        $minimum = config()->integer('results.min_group');
        $tallies = [];

        foreach (self::ORDER as $channel) {
            $row = $rows->get($channel);

            if ($row !== null) {
                $tallies[] = $this->tally($channel, self::count($row->total), self::count($row->delivered), self::count($row->undelivered), $minimum);
            }
        }

        return $tallies;
    }

    private function tally(string $channel, int $total, int $delivered, int $undelivered, int $minimum): ChannelDeliveryTally
    {
        if ($total < $minimum) {
            return new ChannelDeliveryTally($channel, null, null, null, false);
        }

        return $undelivered < $minimum
            ? new ChannelDeliveryTally($channel, $total, null, null, true)
            : new ChannelDeliveryTally($channel, $total, $delivered, $undelivered, false);
    }

    private static function count(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
