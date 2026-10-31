<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\QueueSurveyInvitations;
use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Jobs\SendSurveyInvitations;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

mutates(QueueSurveyInvitations::class, SendSurveyInvitations::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
});

/** A launched evaluation of `$people` people, with its links not sent yet. */
function queuedEvaluation(Tenant $tenant, WorkCenter $plant, App\Models\User $hr, int $people): Campaign
{
    staffOf($tenant, $plant, $people);
    test()->actingAs($hr)->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
    Bus::fake();
    test()->actingAs($hr)->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => '2026-11-20'])->assertSessionHasNoErrors();

    return $campaign->refresh();
}

/** @return list<SendSurveyInvitations> the jobs of the one batch dispatched */
function batchedJobs(): array
{
    $jobs = [];
    Bus::assertBatched(function (PendingBatch $batch) use (&$jobs): bool {
        $jobs = $batch->jobs->all();

        return true;
    });

    return $jobs;
}

function participantsOf(Campaign $campaign): Illuminate\Support\Collection
{
    return Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->get();
}

it('saves the launch and leaves the emails to a batch of jobs of 50 people each, sent after the commit', function (): void {
    $campaign = queuedEvaluation($this->tenant, $this->plant, $this->hr, 120);

    Notification::assertNothingSent();
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 3
        && $batch->jobs->map(fn (SendSurveyInvitations $job): int => count($job->participantIds))->all() === [50, 50, 20]
        && $batch->name === "campaign-invitations:{$campaign->id}"
        && $batch->allowsFailures());
    expect(participantsOf($campaign)->every(fn (Participant $participant): bool => $participant->invitation_status === InvitationStatus::Queued && $participant->token_hash === null))->toBeTrue()
        ->and(participantsOf($campaign)->pluck('invitation_batch')->unique()->all())->toBe([$campaign->invitation_batch]);
});

it('marks who has no email instead of queueing them', function (): void {
    staffOf($this->tenant, $this->plant, 1)->first()->update(['email' => null, 'mobile' => '5512345678']);
    $campaign = queuedEvaluation($this->tenant, $this->plant, $this->hr, 19);

    expect(participantsOf($campaign)->countBy(fn (Participant $participant): string => $participant->invitation_status?->value ?? 'none')->all())
        ->toEqual(['queued' => 19, 'no_email' => 1]);
});

it('gives each person of its block a new link and emails it, once even when run again', function (): void {
    $campaign = queuedEvaluation($this->tenant, $this->plant, $this->hr, 20);
    [$job] = batchedJobs();

    $job->handle();
    $job->handle();

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 20);
    expect(participantsOf($campaign)->every(fn (Participant $participant): bool => $participant->invitation_status === InvitationStatus::Sent
        && $participant->token_hash !== null
        && $participant->invited_at?->equalTo(now()) === true))->toBeTrue();
});

it('sends no link while the company is blocked, and marks who was waiting as failed so it can be sent again', function (): void {
    $campaign = queuedEvaluation($this->tenant, $this->plant, $this->hr, 20);
    [$job] = batchedJobs();
    $this->tenant->update(['deactivated_at' => now()]);

    $job->handle();

    Notification::assertNothingSent();
    expect(participantsOf($campaign)->every(fn (Participant $participant): bool => $participant->invitation_status === InvitationStatus::Failed && $participant->token_hash === null))->toBeTrue();
});

it('leaves to a newer batch whoever it queued again', function (): void {
    $campaign = queuedEvaluation($this->tenant, $this->plant, $this->hr, 20);
    [$job] = batchedJobs();
    Participant::query()->withoutGlobalScopes()->limit(5)->update(['invitation_batch' => (string) Str::uuid()]);

    $job->handle();

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 15);
});

it('marks as failed whoever of its block was still waiting when it gives up', function (): void {
    $campaign = queuedEvaluation($this->tenant, $this->plant, $this->hr, 20);
    [$job] = batchedJobs();
    Participant::query()->withoutGlobalScopes()->limit(4)->update(['invitation_status' => InvitationStatus::Sent]);

    Illuminate\Support\Facades\Log::spy();

    $job->failed(new RuntimeException('550 ana@acme.mx rejected'));

    expect(participantsOf($campaign)->countBy(fn (Participant $participant): string => $participant->invitation_status?->value ?? 'none')->all())
        ->toEqual(['sent' => 4, 'failed' => 16]);
    Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->with('campaign.invitations_failed', ['campaign_id' => $campaign->id, 'failed' => 16, 'exception' => RuntimeException::class]);
});

it('waits for the next minute when the emails per minute are used up', function (): void {
    config(['campaigns.invitations_per_minute' => 3]);
    $campaign = queuedEvaluation($this->tenant, $this->plant, $this->hr, 20);
    [$job] = batchedJobs();
    $job->withFakeQueueInteractions();

    $job->handle();

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 3);
    $job->assertReleased();
    expect(participantsOf($campaign)->where('invitation_status', InvitationStatus::Sent)->count())->toBe(3);
});

it('retries with waits that grow, until a day has passed', function (): void {
    $job = new SendSurveyInvitations('c', 'b', ['p']);

    expect($job->backoff())->toBe([10, 60, 300])
        ->and($job->retryUntil()->equalTo(now()->addDay()))->toBeTrue();
});

it('works in the company that queued it, from a worker with no request', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();
    config(['queue.default' => 'database']);
    RateLimiter::clear('survey-invitations');

    $this->actingAs($this->hr)->post(route('tenant.campaigns.launch', [$this->tenant, $campaign->id]), ['closes_on' => '2026-11-20']);
    Notification::assertNothingSent();
    Context::flush();
    $this->artisan('queue:work', ['--once' => true, '--queue' => 'default']);

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 20);
});

it('sends nothing to whoever has no guide pending any more, and stops waiting for them', function (): void {
    $campaign = queuedEvaluation($this->tenant, $this->plant, $this->hr, 20);
    [$job] = batchedJobs();
    $gone = participantsOf($campaign)->take(3)->pluck('id');
    App\Modules\Campaigns\Models\ParticipantInstrument::query()->withoutGlobalScopes()->whereIn('participant_id', $gone)->update(['status' => 'cancelled', 'cancelled_at' => now()]);

    $job->handle();

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 17);
    expect(Participant::query()->withoutGlobalScopes()->whereKey($gone)->get()->every(fn (Participant $participant): bool => $participant->invitation_status === null && $participant->token_hash === null))->toBeTrue();
});

it('sends nothing once the evaluation is closed or past its last day, marking who waited as failed', function (string $case): void {
    $campaign = queuedEvaluation($this->tenant, $this->plant, $this->hr, 20);
    [$job] = batchedJobs();
    $case === 'closed'
        ? $campaign->update(['status' => App\Modules\Campaigns\Enums\CampaignStatus::Closed, 'closed_at' => now()])
        : $this->travelTo('2026-11-21 12:00:00');

    $job->handle();

    Notification::assertNothingSent();
    expect(participantsOf($campaign)->pluck('invitation_status')->unique()->all())->toBe([InvitationStatus::Failed]);
})->with(['closed', 'past its last day']);
