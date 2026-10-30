<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Support\CampaignInstruments;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Brings an evaluation up to date with the work center's people (criterio C-24): someone hired after the launch takes
 * Guía I and the II, or the III when everyone takes it (never a sample's III outside the draw); someone who left and
 * returned gets their cancelled guides back; and anyone with a guide pending who never got a link, because they had
 * no email, gets one now.
 */
final readonly class InviteMissing
{
    public function __construct(private Roster $roster, private Companies $companies, private SendSurveyInvitation $sendInvitation) {}

    /**
     * @return int how many people got a link.
     */
    public function handle(Campaign $campaign): int
    {
        $people = $this->roster->activeIn($campaign->work_center_id);
        $participants = $campaign->participants()->with('instruments')->get()->keyBy('employee_id');
        $tradeName = $this->companies->current()->tradeName;

        $sent = DB::transaction(function () use ($campaign, $people, $participants, $tradeName): int {
            $sent = 0;

            foreach ($people as $person) {
                $participant = $participants->get($person->id);
                $owed = $participant === null ? $this->join($campaign, $person) : $this->catchUp($participant);

                if ($owed !== null && $person->email !== null) {
                    $this->sendInvitation->handle($campaign, $owed, $person->email, $tradeName);
                    $sent++;
                }
            }

            return $sent;
        });

        Log::info('campaign.missing_invited', ['campaign_id' => $campaign->id, 'sent' => $sent]);

        return $sent;
    }

    private function join(Campaign $campaign, RosterEntry $person): Participant
    {
        $participant = $campaign->participants()->create(['tenant_id' => $campaign->tenant_id, 'employee_id' => $person->id]);
        CampaignInstruments::give($participant, CampaignInstruments::for($campaign, inSample: false));

        return $participant;
    }

    /**
     * Gives back the guides cancelled while the person was away. Returns them when they are owed a link.
     */
    private function catchUp(Participant $participant): ?Participant
    {
        $restored = $participant->instruments()
            ->where('status', InstrumentStatus::Cancelled)
            ->update(['status' => InstrumentStatus::Pending, 'cancelled_at' => null]);
        $neverInvited = $participant->invited_at === null && $participant->instruments->contains('status', InstrumentStatus::Pending);

        return $restored > 0 || $neverInvited ? $participant : null;
    }
}
