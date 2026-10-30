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
}
