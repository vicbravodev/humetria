<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Jobs;

use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the survey link of a block of people: each gets a new link (the one before stops working) and an email with
 * it. It runs in the company that queued it (its id travels in the job's Context). Only who is still waiting in this
 * job's batch is sent to, so running it again, or late after a newer batch, never sends a link twice; it can only
 * happen if the worker dies between sending an email and writing it down. It waits for the next minute when the
 * emails per minute are used up, and gives up after a day, marking who was left as failed.
 */
final class SendSurveyInvitations implements ShouldQueue
{
    use Batchable, Queueable;

    private const string LIMITER = 'survey-invitations';

    public int $maxExceptions = 3;

    /**
     * @param  list<string>  $participantIds
     */
    public function __construct(
        public readonly string $campaignId,
        public readonly string $invitationBatch,
        public readonly array $participantIds,
    ) {}

    public function handle(): void
    {
        $campaign = Campaign::query()->find($this->campaignId);

        if ($campaign === null || $campaign->closes_on === null) {
            return;
        }

        $waiting = $this->waiting()->get();
        $people = resolve(Roster::class)->findMany(array_values($waiting->map(fn (Participant $participant): string => $participant->employee_id)->all()));
        $tradeName = resolve(Companies::class)->current()->tradeName;
        $lastDay = CampaignClock::day($campaign->closes_on, isDate: true);

        foreach ($waiting as $participant) {
            $email = $people[$participant->employee_id]->email ?? null;

            if ($email === null) {
                $participant->update(['invitation_status' => InvitationStatus::NoEmail]);

                continue;
            }

            if (RateLimiter::tooManyAttempts(self::LIMITER, config()->integer('campaigns.invitations_per_minute'))) {
                $this->release(RateLimiter::availableIn(self::LIMITER));

                return;
            }

            RateLimiter::hit(self::LIMITER);
            $token = Str::random(64);
            $participant->update(['token_hash' => Participant::hashToken($token), 'invited_at' => now()]);
            Notification::route('mail', $email)->notify(new SurveyInvitation($tradeName, $token, $lastDay));
            $participant->update(['invitation_status' => InvitationStatus::Sent]);
        }

        Log::info('campaign.invitations_sent', ['campaign_id' => $campaign->id, 'sent' => $waiting->count()]);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDay();
    }

    public function failed(?Throwable $exception): void
    {
        $failed = $this->waiting()->update(['invitation_status' => InvitationStatus::Failed]);
        Log::warning('campaign.invitations_failed', ['campaign_id' => $this->campaignId, 'failed' => $failed, 'exception' => $exception?->getMessage()]);
    }

    /**
     * Who of this block is still waiting in this job's batch.
     *
     * @return Builder<Participant>
     */
    private function waiting(): Builder
    {
        return Participant::query()
            ->whereKey($this->participantIds)
            ->where('invitation_status', InvitationStatus::Queued)
            ->where('invitation_batch', $this->invitationBatch);
    }
}
