<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Support\CampaignInstruments;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Random\Randomizer;

/**
 * Adds people to a Guía III sample when some of the sampled will not answer, so the answers can still reach the
 * minimum of III.1 (III.2 c). They are drawn at random from the active people outside the sample of the same gender as
 * who they replace, so the sample keeps its split by gender (criterio C-10), and take Guía III.
 */
final readonly class DrawReplacements
{
    public function __construct(
        private Roster $roster,
        private QueueSurveyInvitations $queueInvitations,
    ) {}

    /**
     * @throws ValidationException when fewer people are left outside the sample.
     */
    public function handle(Campaign $campaign, string $gender, int $count): int
    {
        $available = $this->available($campaign)[$gender] ?? [];

        if ($count > count($available)) {
            throw ValidationException::withMessages(['count' => match (count($available)) {
                0 => 'Ya no queda nadie de ese género fuera de la muestra.',
                1 => 'Solo queda 1 persona de ese género fuera de la muestra.',
                default => 'Solo quedan '.count($available).' personas de ese género fuera de la muestra.',
            }]);
        }

        DB::transaction(function () use ($campaign, $available, $count): void {
            $drawn = [];

            foreach (array_slice((new Randomizer)->shuffleArray($available), 0, $count) as $person) {
                $drawn[$this->enroll($campaign, $person)->id] = $person->email !== null;
            }

            $this->queueInvitations->handle($campaign, $drawn);
        });

        Log::info('campaign.replacements_drawn', ['campaign_id' => $campaign->id, 'gender' => $gender, 'count' => $count]);

        return $count;
    }

    /**
     * The active people of the work center outside the sample, by gender.
     *
     * @return array<string, list<RosterEntry>>
     */
    public function available(Campaign $campaign): array
    {
        $sampled = array_flip($campaign->participants()->where('in_sample', true)->get(['employee_id'])->map(fn (Participant $participant): string => $participant->employee_id)->all());

        return DrawSample::strata(array_values(array_filter(
            $this->roster->activeIn($campaign->work_center_id),
            fn (RosterEntry $person): bool => ! isset($sampled[$person->id]),
        )));
    }

    private function enroll(Campaign $campaign, RosterEntry $person): Participant
    {
        $participant = $campaign->participants()->firstOrCreate(['employee_id' => $person->id], ['tenant_id' => $campaign->tenant_id]);
        $participant->update(['in_sample' => true]);
        CampaignInstruments::give($participant, CampaignInstruments::for($campaign, inSample: true));

        return $participant;
    }
}
