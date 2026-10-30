<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Support\CampaignInstruments;
use App\Modules\Instruments\Contracts\Nom035Sample;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Random\Randomizer;

/**
 * Adds people to a Guía III sample when some of the sampled will not answer, so the answers can still reach the
 * minimum of III.1 (III.2 c). They are drawn at random from the active people outside the sample, split by gender in
 * proportion to who is left, and take Guía III.
 */
final readonly class DrawReplacements
{
    public function __construct(
        private Roster $roster,
        private Nom035Sample $sample,
        private Companies $companies,
        private SendSurveyInvitation $sendInvitation,
    ) {}

    /**
     * @throws ValidationException when fewer people are left outside the sample.
     */
    public function handle(Campaign $campaign, int $count): int
    {
        $sampled = array_flip($campaign->participants()->where('in_sample', true)->get(['employee_id'])->map(fn (Participant $participant): string => $participant->employee_id)->all());
        $available = array_values(array_filter($this->roster->activeIn($campaign->work_center_id), fn (RosterEntry $person): bool => ! isset($sampled[$person->id])));

        if ($count > count($available)) {
            throw ValidationException::withMessages(['count' => count($available) === 1
                ? 'Solo queda 1 persona fuera de la muestra.'
                : 'Solo quedan '.count($available).' personas fuera de la muestra.']);
        }

        $strata = DrawSample::strata($available);
        $quotas = $this->sample->allocate($count, array_map(count(...), $strata));
        $randomizer = new Randomizer;
        $tradeName = $this->companies->current()->tradeName;

        DB::transaction(function () use ($campaign, $strata, $quotas, $randomizer, $tradeName): void {
            foreach ($strata as $gender => $members) {
                foreach (array_slice($randomizer->shuffleArray($members), 0, $quotas[$gender]) as $person) {
                    $participant = $this->enroll($campaign, $person);

                    if ($person->email !== null) {
                        $this->sendInvitation->handle($campaign, $participant, $person->email, $tradeName);
                    }
                }
            }
        });

        Log::info('campaign.replacements_drawn', ['campaign_id' => $campaign->id, 'count' => $count]);

        return $count;
    }

    private function enroll(Campaign $campaign, RosterEntry $person): Participant
    {
        $participant = $campaign->participants()->firstOrCreate(['employee_id' => $person->id], ['tenant_id' => $campaign->tenant_id]);
        $participant->update(['in_sample' => true]);
        CampaignInstruments::give($participant, CampaignInstruments::for($campaign, inSample: true));

        return $participant;
    }
}
