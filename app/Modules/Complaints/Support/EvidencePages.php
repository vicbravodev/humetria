<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ChannelStatus;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * The page a company shows an inspector to prove it has a safe and confidential mechanism (NOM-035 8.1 b, 10.2): how the channel
 * is set up and that it operates, never what a single case says. It carries no text of a complaint, no reference and no person.
 */
final readonly class EvidencePages
{
    public function __construct(private Companies $companies, private ActiveHandlers $active, private HandlerCoverage $coverage, private ComplaintStatistics $statistics) {}

    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        $channel = ComplaintChannel::query()->first();
        $handlers = $this->active->all();
        $counts = $this->coverage->counts();

        return [
            'company' => $this->companies->current()->tradeName,
            'channel' => [
                'status' => ($channel?->status() ?? ChannelStatus::Inactive)->value,
                'url' => $channel?->enabled_at === null ? null : $channel->publicUrl(),
                'publishedOn' => $channel?->published_on?->toDateString(),
                'procedure' => $channel->procedure ?? '',
                'acknowledgeWithinDays' => $channel->acknowledge_within_days ?? config()->integer('complaints.deadlines.acknowledge_days'),
                'resolveWithinDays' => $channel->resolve_within_days ?? config()->integer('complaints.deadlines.resolve_days'),
                'retentionMonths' => $channel->retention_months ?? config()->integer('complaints.retention.default_months'),
            ],
            'handlers' => [
                'count' => count($handlers),
                'independent' => array_filter($handlers, fn (array $row): bool => ! in_array($row['member']->role->value, ['hr', 'owner'], true)) !== [],
            ],
            'types' => array_map(fn (ComplaintType $type): array => ['value' => $type->value, 'label' => $type->label()], ComplaintType::cases()),
            'coverage' => array_map(fn (RespondentKind $kind): array => ['kind' => $kind->value, 'label' => $kind->shortLabel(), 'count' => $counts[$kind->value]], RespondentKind::cases()),
            'summary' => $this->statistics->summary(),
            'generatedOn' => Dates::today()->toDateString(),
        ];
    }
}
