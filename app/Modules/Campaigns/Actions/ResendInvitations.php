<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Exceptions\CampaignStateChanged;
use App\Modules\Campaigns\Exceptions\ReminderTooSoon;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Organization\Contracts\Roster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sends the link again to everyone with a guide pending and an email (or to some people). It is the link they already
 * had: someone halfway through the survey keeps their place.
 */
final readonly class ResendInvitations
{
    public function __construct(private Roster $roster, private QueueSurveyInvitations $queueInvitations) {}

    /**
     * @param  list<string>|null  $employeeIds  only these people, when given.
     * @param  bool  $reminder  a reminder to the people with a guide pending: one per wait, whoever asks (the retry of a failed link or the link of one person is not).
     * @return int how many people got a new link.
     *
     * @throws CampaignStateChanged when another request closed it meanwhile.
     * @throws ReminderTooSoon when a reminder went out less than the wait ago.
     */
    public function handle(Campaign $campaign, ?array $employeeIds = null, bool $reminder = false): int
    {
        $sent = DB::transaction(function () use ($campaign, $employeeIds, $reminder): int {
            // Under lock: two reminders from two tabs at the same moment queue here, and the second finds the first one's time.
            $campaign = Campaign::lockIn($campaign, CampaignStatus::Active);
            $reminder && self::guardWait($campaign);
            $participants = $campaign->participants()
                ->when($employeeIds !== null, fn ($query) => $query->among('campaign_participants.employee_id', $employeeIds ?? []))
                ->whereHas('instruments', fn ($query) => $query->where('status', InstrumentStatus::Pending))
                ->get();
            $people = $this->roster->findMany(array_values($participants->map(fn (Participant $participant): string => $participant->employee_id)->all()));
            $reached = [];

            foreach ($participants as $participant) {
                $person = $people[$participant->employee_id] ?? null;

                if ($person !== null && SurveyInvitation::routes($person) !== []) {
                    $reached[$participant->id] = true;
                }
            }

            $sent = $this->queueInvitations->handle($campaign, $reached);
            $reminder && $sent > 0 && $campaign->update(['last_resent_at' => now()]);

            return $sent;
        }, Campaign::DEADLOCK_ATTEMPTS);

        Log::info('campaign.invitations_resent', ['campaign_id' => $campaign->id, 'sent' => $sent]);

        return $sent;
    }

    /**
     * @throws ReminderTooSoon
     */
    private static function guardWait(Campaign $campaign): void
    {
        $next = $campaign->last_resent_at?->addHours(config()->integer('campaigns.resend_cooldown_hours'));

        if ($next !== null && $next->isFuture()) {
            throw new ReminderTooSoon((int) ceil(now()->diffInMinutes($next, absolute: true) / 60));
        }
    }
}
