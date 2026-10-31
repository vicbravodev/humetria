<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Exceptions\CampaignStateChanged;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Campaigns\Support\CampaignInstruments;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
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
    public function __construct(private Roster $roster, private QueueSurveyInvitations $queueInvitations) {}

    /**
     * @return int how many people got a link.
     *
     * @throws CampaignStateChanged when another request closed it meanwhile.
     */
    public function handle(Campaign $campaign): int
    {
        $sent = DB::transaction(function () use ($campaign): int {
            // Under lock: closing it meanwhile waits here, or this finds it closed.
            $campaign = Campaign::lockIn($campaign, CampaignStatus::Active);
            $owed = [];

            foreach ($this->owed($campaign) as [$person, $participant]) {
                $participant = $participant === null ? $this->join($campaign, $person) : $this->restore($participant);
                $owed[$participant->id] = SurveyInvitation::routes($person) !== [];
            }

            return $this->queueInvitations->handle($campaign, $owed);
        }, Campaign::DEADLOCK_ATTEMPTS);

        Log::info('campaign.missing_invited', ['campaign_id' => $campaign->id, 'sent' => $sent]);

        return $sent;
    }

    /**
     * How many people handle() would send a link to now, changing nothing.
     */
    public function count(Campaign $campaign): int
    {
        return count(array_filter($this->owed($campaign), fn (array $owed): bool => SurveyInvitation::routes($owed[0]) !== []));
    }

    /**
     * The active people of the work center owed a link, with their place in the campaign (null for someone hired after
     * the launch): someone who returned, with guides cancelled while away, and whoever has a guide pending and never got
     * a link.
     *
     * @return list<array{RosterEntry, ?Participant}>
     */
    private function owed(Campaign $campaign): array
    {
        $participants = $campaign->participants()->with('instruments')->get()->keyBy('employee_id');
        $owed = [];

        foreach ($this->roster->activeIn($campaign->work_center_id) as $person) {
            $participant = $participants->get($person->id);
            $returned = $participant?->instruments->contains('status', InstrumentStatus::Cancelled) ?? false;
            $neverInvited = $participant !== null && in_array($participant->invitation_status, [null, InvitationStatus::NoEmail], true)
                && $participant->instruments->contains('status', InstrumentStatus::Pending);

            if ($participant === null || $returned || $neverInvited) {
                $owed[] = [$person, $participant];
            }
        }

        return $owed;
    }

    private function join(Campaign $campaign, RosterEntry $person): Participant
    {
        $participant = $campaign->participants()->create(['tenant_id' => $campaign->tenant_id, 'employee_id' => $person->id]);
        CampaignInstruments::give($participant, CampaignInstruments::for($campaign, inSample: false));

        return $participant;
    }

    /**
     * Gives back the guides cancelled while the person was away.
     */
    private function restore(Participant $participant): Participant
    {
        $participant->instruments()
            ->where('status', InstrumentStatus::Cancelled)
            ->update(['status' => InstrumentStatus::Pending, 'cancelled_at' => null]);

        return $participant;
    }
}
