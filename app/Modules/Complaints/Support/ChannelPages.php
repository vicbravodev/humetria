<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ChannelStatus;
use App\Modules\Complaints\Models\ComplaintChannel;

/** What the channel page of the company shows. */
final readonly class ChannelPages
{
    public function __construct(private UnassignedCases $unassigned) {}

    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        $channel = ComplaintChannel::query()->first();

        return [
            'unassigned' => $this->unassigned->count(),
            'channel' => [
                'status' => ($channel?->status() ?? ChannelStatus::Inactive)->value,
                'url' => $channel?->enabled_at === null ? null : $channel->publicUrl(),
                'publishedOn' => $channel?->published_on?->toDateString(),
                'codeRotatedOn' => $channel?->access_code_rotated_on?->toDateString(),
            ],
            'settings' => [
                'procedure' => $channel->procedure ?? ProcedureModel::text(),
                'acknowledgeWithinDays' => $channel->acknowledge_within_days ?? config()->integer('complaints.deadlines.acknowledge_days'),
                'resolveWithinDays' => $channel->resolve_within_days ?? config()->integer('complaints.deadlines.resolve_days'),
                'retentionMonths' => $channel->retention_months ?? config()->integer('complaints.retention.default_months'),
            ],
            'bounds' => [
                'acknowledgeWithinDays' => ['min' => 1, 'max' => 15],
                'resolveWithinDays' => ['min' => 7, 'max' => 180],
                'retentionMonths' => ['min' => config()->integer('complaints.retention.min_months'), 'max' => config()->integer('complaints.retention.max_months')],
            ],
        ];
    }
}
