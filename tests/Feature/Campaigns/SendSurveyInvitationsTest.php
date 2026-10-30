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
        ->toBe(['queued' => 19, 'no_email' => 1]);
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

    $job->failed(new RuntimeException('SMTP down'));

    expect(participantsOf($campaign)->countBy(fn (Participant $participant): string => $participant->invitation_status?->value ?? 'none')->all())
        ->toBe(['sent' => 4, 'failed' => 16]);
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
