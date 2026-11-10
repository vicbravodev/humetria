<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Jobs;

use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation;
use App\Modules\Engagement\Support\SurveyClock;
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
use Throwable;

/**
 * Sends the survey link of a block of people: each gets an email with their link, the same one any earlier email
 * carried, so a reminder never takes away the link of someone halfway through. It runs in the company that queued it (its
 * id travels in the job's Context). Only who is still waiting in this job's batch is sent to, so running it again, or
 * late after a newer batch, never sends a link twice; it can only happen if the worker dies between sending an email
 * and writing it down. Nobody gets a link once the survey is closed or past its last day. It waits for the next minute
 * when the emails per minute are used up, and gives up after a day, marking who was left as failed.
 */
final class SendInvitations implements ShouldQueue
{
    use Batchable, Queueable;

    /** The same bucket as the NOM-035 invitations: the cap is the mail provider's, not a module's. */
    private const string LIMITER = 'survey-invitations';

    public int $maxExceptions = 3;

    /**
     * @param  list<string>  $invitationIds
     */
    public function __construct(
        public readonly string $surveyId,
        public readonly string $invitationBatch,
        public readonly array $invitationIds,
    ) {}

    public function handle(): void
    {
        $survey = Survey::query()->find($this->surveyId);

        if ($survey === null) {
            return;
        }

        // Closed, or past its last day: a link would only open on «cerrada».
        if ($survey->status !== SurveyStatus::Active || $survey->closes_on === null || SurveyClock::hasPassed($survey->closes_on)) {
            $this->failed(null);

            return;
        }

        $companies = resolve(Companies::class);
        $company = $companies->current();

        // A blocked company sends nothing; who was waiting is marked failed, so a reminder reaches them once it is back.
        if ($company->blocked) {
            $this->failed(null);

            return;
        }

        $waiting = $this->waiting()->get();
        $people = resolve(Roster::class)->findMany(array_values($waiting->map(fn (Invitation $invitation): string => $invitation->employee_id)->all()));
        $contact = $companies->contact();

        foreach ($waiting as $invitation) {
            $person = $people[$invitation->employee_id] ?? null;

            if ($person === null || ($person->email ?? '') === '') {
                $invitation->update(['invitation_status' => InvitationStatus::NoEmail]);

                continue;
            }

            // Counted first and then compared, in one atomic step, so workers sending at once never pass the limit together.
            if (RateLimiter::hit(self::LIMITER) > config()->integer('campaigns.invitations_per_minute')) {
                $this->release(RateLimiter::availableIn(self::LIMITER));

                return;
            }

            $token = $invitation->surveyToken();
            $reminder = $invitation->invited_at !== null;
            $invitation->update(['token_hash' => Invitation::hashToken($token), 'invited_at' => now()]);
            Notification::routes(['mail' => $person->email])->notify(new SurveyInvitation(
                $reminder, $token, $person->name, $company, $contact, $survey->closes_on, $survey->kind,
            ));
            $invitation->update(['invitation_status' => InvitationStatus::Sent]);
        }

        Log::info('engagement.invitations_sent', ['survey_id' => $survey->id, 'sent' => $waiting->count()]);
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
        // The exception's class only: a mail error's message can carry the address.
        Log::warning('engagement.invitations_failed', ['survey_id' => $this->surveyId, 'failed' => $failed, 'exception' => $exception === null ? null : $exception::class]);
    }

    /**
     * Who of this block is still waiting in this job's batch.
     *
     * @return Builder<Invitation>
     */
    private function waiting(): Builder
    {
        return Invitation::query()
            ->whereKey($this->invitationIds)
            ->where('invitation_status', InvitationStatus::Queued)
            ->where('invitation_batch', $this->invitationBatch);
    }
}
