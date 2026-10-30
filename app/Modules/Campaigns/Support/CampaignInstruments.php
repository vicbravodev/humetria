<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use Illuminate\Support\Str;

/**
 * Which guides of a campaign a person takes: all of them, except Guía III in a sampled evaluation, which only the
 * sampled people take (III.1).
 */
final class CampaignInstruments
{
    /** Rows per insert: well under Postgres' 65,535 bound parameters for seven columns. */
    private const int ROWS_PER_INSERT = 1000;

    /**
     * @return list<string>
     */
    public static function for(Campaign $campaign, bool $inSample): array
    {
        return array_values(array_filter(
            $campaign->instruments,
            fn (string $key): bool => $key !== 'nom035.guide-iii' || $campaign->population !== Population::Sample || $inSample,
        ));
    }

    /**
     * Gives a participant the guides they do not have yet, pending.
     *
     * @param  list<string>  $instruments
     */
    public static function give(Participant $participant, array $instruments): void
    {
        $has = $participant->instruments()->get(['instrument'])->map(fn (ParticipantInstrument $guide): string => $guide->instrument)->all();
        $now = now();

        $participant->instruments()->insert(array_map(fn (string $instrument): array => [
            'id' => Str::uuid()->toString(),
            'tenant_id' => $participant->tenant_id,
            'participant_id' => $participant->id,
            'instrument' => $instrument,
            'status' => InstrumentStatus::Pending->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values(array_diff($instruments, $has))));
    }

    /**
     * Makes everyone given a participant of the campaign with their guides pending, in a few queries whatever their
     * number: those already in it (the sampled ones of a draft) keep their row and get only the guides they lack.
     *
     * @param  array<string, bool>  $people  employee id => whether they are in the Guía III sample
     * @return array<string, string> employee id => participant id
     */
    public static function enroll(Campaign $campaign, array $people): array
    {
        $now = now();
        $participants = [];

        foreach ($campaign->participants()->get(['id', 'employee_id']) as $participant) {
            $participants[$participant->employee_id] = $participant->id;
        }

        $new = [];

        foreach (array_keys($people) as $employeeId) {
            if (! isset($participants[$employeeId])) {
                $participants[$employeeId] = Str::uuid()->toString();
                $new[] = ['id' => $participants[$employeeId], 'tenant_id' => $campaign->tenant_id, 'campaign_id' => $campaign->id, 'employee_id' => $employeeId, 'in_sample' => false, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        foreach (array_chunk($new, self::ROWS_PER_INSERT) as $rows) {
            Participant::query()->insert($rows);
        }

        $has = [];

        foreach (ParticipantInstrument::query()->whereHas('participant', fn ($query) => $query->where('campaign_id', $campaign->id))->get(['participant_id', 'instrument']) as $guide) {
            $has[$guide->participant_id][$guide->instrument] = true;
        }

        $guides = [];

        foreach ($people as $employeeId => $inSample) {
            foreach (self::for($campaign, $inSample) as $instrument) {
                if (! isset($has[$participants[$employeeId]][$instrument])) {
                    $guides[] = ['id' => Str::uuid()->toString(), 'tenant_id' => $campaign->tenant_id, 'participant_id' => $participants[$employeeId], 'instrument' => $instrument, 'status' => InstrumentStatus::Pending->value, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }

        foreach (array_chunk($guides, self::ROWS_PER_INSERT) as $rows) {
            ParticipantInstrument::query()->insert($rows);
        }

        return array_intersect_key($participants, $people);
    }
}
