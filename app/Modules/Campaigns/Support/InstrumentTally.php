<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Models\ParticipantInstrument;

/**
 * How many guides of each campaign are pending, answered or cancelled, in one query.
 */
final class InstrumentTally
{
    /**
     * @param  list<string>  $campaignIds
     * @return array<string, array<string, array<string, int>>> campaign id → instrument → status → count
     */
    public function of(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $tally = [];
        $rows = ParticipantInstrument::query()
            ->join('campaign_participants', 'campaign_participants.id', '=', 'participant_instruments.participant_id')
            ->whereIn('campaign_participants.campaign_id', $campaignIds)
            ->groupBy('campaign_participants.campaign_id', 'participant_instruments.instrument', 'participant_instruments.status')
            ->selectRaw('campaign_participants.campaign_id, participant_instruments.instrument, participant_instruments.status, count(*)::int as total')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            if (is_string($row->campaign_id) && is_string($row->instrument) && is_string($row->status) && is_int($row->total)) {
                $tally[$row->campaign_id][$row->instrument][$row->status] = $row->total;
            }
        }

        return $tally;
    }

    /**
     * How many guides of a campaign are pending with no link sent: their person has no email.
     *
     * @return array<string, int> instrument → count
     */
    public function unreachable(string $campaignId): array
    {
        $counts = [];
        $rows = ParticipantInstrument::query()
            ->join('campaign_participants', 'campaign_participants.id', '=', 'participant_instruments.participant_id')
            ->where('campaign_participants.campaign_id', $campaignId)
            ->where(fn ($query) => $query->whereNull('campaign_participants.invitation_status')->orWhere('campaign_participants.invitation_status', InvitationStatus::NoEmail))
            ->where('participant_instruments.status', InstrumentStatus::Pending)
            ->groupBy('participant_instruments.instrument')
            ->selectRaw('participant_instruments.instrument, count(*)::int as total')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            if (is_string($row->instrument) && is_int($row->total)) {
                $counts[$row->instrument] = $row->total;
            }
        }

        return $counts;
    }
}
