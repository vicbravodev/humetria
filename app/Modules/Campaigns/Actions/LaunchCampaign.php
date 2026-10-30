<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Actions\RecordAudit;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Exceptions\DraftOutdated;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Support\CampaignInstruments;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Instruments\Contracts\Nom035Sample;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Launches a NOM-035 evaluation: everyone active in the work center takes Guía I, and Guía II or, with Guía III,
 * everyone or the sampled people (7.1, II.1, III.1). The draft is checked against the roster as it is now: if the
 * guides or the sample would come out different, it is not launched (criterio C-10). Each person with an email gets
 * their link; those without one wait until RH adds it.
 */
final readonly class LaunchCampaign
{
    public function __construct(
        private Roster $roster,
        private Nom035Guides $guides,
        private Nom035Sample $sample,
        private SendSurveyInvitation $sendInvitation,
        private RecordAudit $recordAudit,
        private Companies $companies,
    ) {}

    /**
     * @throws ValidationException when the roster changed since the draft.
     */
    public function handle(Campaign $campaign, CarbonImmutable $closesOn): void
    {
        if ($campaign->status !== CampaignStatus::Draft) {
            throw new LogicException('Only a draft is launched.');
        }

        $people = $this->roster->activeIn($campaign->work_center_id);
        $this->assertDraftStillHolds($campaign, $people);
        $tradeName = $this->companies->current()->tradeName;

        DB::transaction(function () use ($campaign, $people, $closesOn, $tradeName): void {
            $campaign->update(['status' => CampaignStatus::Active, 'launched_at' => now(), 'closes_on' => $closesOn, 'workers' => count($people)]);
            $sampled = array_flip($this->sampledIds($campaign));
            $invited = 0;

            foreach ($people as $person) {
                $participant = $campaign->participants()->firstOrCreate(['employee_id' => $person->id], ['tenant_id' => $campaign->tenant_id]);
                CampaignInstruments::give($participant, CampaignInstruments::for($campaign, isset($sampled[$person->id])));

                if ($person->email !== null) {
                    $this->sendInvitation->handle($campaign, $participant, $person->email, $tradeName);
                    $invited++;
                }
            }

            $counts = ['workers' => count($people), 'participants' => count($people), 'invited' => $invited];
            Log::info('campaign.launched', ['campaign_id' => $campaign->id, ...$counts]);
            $this->recordAudit->handle('campaign.launched', $campaign, [
                ...$counts,
                'instruments' => $campaign->instruments,
                'population' => $campaign->population?->value,
                'sample_size' => $campaign->sample_size,
            ]);
        });
    }

    /**
     * @param  list<RosterEntry>  $people
     */
    private function assertDraftStillHolds(Campaign $campaign, array $people): void
    {
        if ($people === [] || $this->guides->instruments(count($people)) !== $campaign->instruments) {
            throw DraftOutdated::guides();
        }

        if ($campaign->population !== Population::Sample) {
            return;
        }

        $quotas = $this->sample->allocate($this->sample->size(count($people)), array_map(count(...), DrawSample::strata($people)));
        $active = array_flip(array_map(fn (RosterEntry $person): string => $person->id, $people));
        $sampled = $this->sampledIds($campaign);
        $drawnStillHere = array_filter($sampled, fn (string $id): bool => isset($active[$id]));

        if (array_sum($quotas) !== $campaign->sample_size || ! self::sameQuotas($quotas, $campaign->quotas) || count($drawnStillHere) !== count($sampled)) {
            throw DraftOutdated::sample();
        }
    }

    /**
     * Same genders with the same quotas, in any order: jsonb keeps keys in its own order.
     *
     * @param  array<string, int>  $now
     * @param  array<string, int>|null  $drawn
     */
    private static function sameQuotas(array $now, ?array $drawn): bool
    {
        if ($drawn === null) {
            return false;
        }

        ksort($now);
        ksort($drawn);

        return $now === $drawn;
    }

    /**
     * Before launch, a draft's only participants are the people its sample drew.
     *
     * @return list<string>
     */
    private function sampledIds(Campaign $campaign): array
    {
        return array_values($campaign->participants()->get(['employee_id'])->map(fn (Participant $participant): string => $participant->employee_id)->all());
    }
}
