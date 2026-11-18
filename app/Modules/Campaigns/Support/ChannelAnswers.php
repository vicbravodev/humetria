<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Support\SmallCells;

/**
 * Who answered, by the channel their invitation went through, for one campaign. Counts of people only, and the table is hidden
 * whole when any cell is under the minimum (SmallCells). A person counts as answered when at least one guide is completed.
 */
final class ChannelAnswers
{
    /** @return list<array{channel: string, answered: int, unanswered: int}>|null null when the table is hidden. */
    public function handle(Campaign $campaign): ?array
    {
        $rows = Participant::query()->toBase()
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('invited_via')
            ->groupBy('invited_via')
            ->selectRaw(
                'invited_via, '
                .'count(*) filter (where exists (select 1 from participant_instruments pi where pi.participant_id = campaign_participants.id and pi.status = ?)) as answered, '
                .'count(*) filter (where not exists (select 1 from participant_instruments pi where pi.participant_id = campaign_participants.id and pi.status = ?)) as unanswered',
                [InstrumentStatus::Completed->value, InstrumentStatus::Completed->value],
            )
            ->get();

        $cells = [];

        foreach ($rows as $row) {
            if (is_string($row->invited_via)) {
                $cells[$row->invited_via] = ['answered' => is_int($row->answered) ? $row->answered : 0, 'unanswered' => is_int($row->unanswered) ? $row->unanswered : 0];
            }
        }

        return SmallCells::answeredByChannel($cells, config()->integer('results.min_group'));
    }
}
