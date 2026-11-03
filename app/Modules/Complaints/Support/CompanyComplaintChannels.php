<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Contracts\ComplaintChannels;
use App\Modules\Complaints\Data\ChannelData;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Models\ComplaintChannel;
use Carbon\CarbonImmutable;

final readonly class CompanyComplaintChannels implements ComplaintChannels
{
    public function current(): ChannelData
    {
        $channel = ComplaintChannel::query()->first();
        $open = $channel?->isOpen() === true;

        return new ChannelData(
            available: $open,
            url: $open ? $channel->publicUrl() : null,
            publishedOn: $channel?->published_on === null ? null : CarbonImmutable::parse($channel->published_on),
            types: array_map(fn (ComplaintType $type): string => $type->value, ComplaintType::cases()),
            anonymousAllowed: true,
            acknowledgeWithinDays: $channel->acknowledge_within_days ?? config()->integer('complaints.deadlines.acknowledge_days'),
            resolveWithinDays: $channel->resolve_within_days ?? config()->integer('complaints.deadlines.resolve_days'),
            procedure: $channel->procedure ?? ProcedureModel::text(),
        );
    }
}
