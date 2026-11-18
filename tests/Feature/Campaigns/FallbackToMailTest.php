<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Campaigns\Actions\OpenSurvey;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Exceptions\SurveyUnavailable;
use App\Modules\Campaigns\Listeners\FallBackToMail;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantChannel;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

mutates(FallBackToMail::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Notification::fake();
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')],
        'billing.versions.v1.plans.cumplimiento.quotas.whatsapp' => 100]);
    [, $account] = paidCompany();
    $this->tenant = planFor($account, tenant: Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);
    TenantChannelFactory::new()->for($this->tenant)->create(['channel' => 'mail', 'preference' => 2]);

    $staff = staffOf($this->tenant, $this->plant, 6)->values();
    $staff->each(fn (Employee $employee, int $i) => $employee->update(['mobile' => '+52818687'.str_pad((string) (5700 + $i), 4, '0', STR_PAD_LEFT), 'email' => "persona{$i}@example.test"]));
    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id]);
    $this->campaign = Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
    // The queue is sync: the batch of invitations runs as the launch commits, and WhatsApp is the channel of everyone.
    $this->actingAs($this->hr)->post(route('tenant.campaigns.launch', [$this->tenant, $this->campaign->id]), ['closes_on' => '2026-11-20'])->assertSessionHasNoErrors();
    Notification::fake();
    $this->participant = Participant::query()->withoutGlobalScopes()->where('campaign_id', $this->campaign->id)->orderBy('id')->firstOrFail();
    $this->employee = Employee::query()->withoutGlobalScopes()->findOrFail($this->participant->employee_id);
    $this->oldToken = $this->participant->surveyToken();
});

function waFailure(Participant $participant, Campaign $campaign, FailureCause $cause = FailureCause::NoWhatsappAccount, ?string $type = null): WhatsAppMessageFailed
{
    return new WhatsAppMessageFailed(
        (string) Str::uuid(),
        $participant->tenant_id,
        CarbonImmutable::now(),
        new InvitationReference($type ?? InvitationReference::CAMPAIGN_PARTICIPANT, $participant->id, InvitationReference::CAMPAIGN, $campaign->id),
        $cause,
    );
}

function mailReserved(Tenant $tenant): int
{
    return (int) UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', 'email_campaign')->sum('reserved');
}

it('rotates the link and mails the person when the number has no WhatsApp (CUJ 3)', function (): void {
    Log::spy();
    $reservedBefore = mailReserved($this->tenant);

    event(waFailure($this->participant, $this->campaign));

    $participant = $this->participant->refresh();
    $newToken = $participant->surveyToken();
    expect($newToken)->not->toBe($this->oldToken)
        ->and($participant->invited_via)->toBe('mail')
        ->and($participant->token_version)->toBe(1)
        ->and($participant->token_hash)->toBe(Participant::hashToken($newToken))
        ->and(mailReserved($this->tenant))->toBe($reservedBefore + 1)
        ->and(fn () => resolve(OpenSurvey::class)->handle($this->oldToken))->toThrow(SurveyUnavailable::class)
        ->and(resolve(OpenSurvey::class)->handle($newToken)->participant->id)->toBe($participant->id);
    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 1);
    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $mail, array $channels, AnonymousNotifiable $notifiable): bool {
        return $channels === ['mail'] && $notifiable->routes === ['mail' => $this->employee->email]
            && str_contains((string) $mail->toMail($notifiable)->actionUrl, $this->participant->refresh()->surveyToken());
    });
});

it('does not put the phone or the message of Meta in the logs', function (): void {
    Log::spy();

    event(waFailure($this->participant, $this->campaign));

    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => ! str_contains((string) json_encode($context), (string) $this->employee->mobile)
        && ! str_contains((string) json_encode($context), '8186875'));
});

it('does nothing for a cause the mail does not take over from', function (FailureCause $cause): void {
    event(waFailure($this->participant, $this->campaign, $cause));

    Notification::assertNothingSent();
    expect($this->participant->refresh()->token_version)->toBe(0)->and($this->participant->invited_via)->toBe('whatsapp');
})->with([FailureCause::Paused, FailureCause::InvitationClosed, FailureCause::OutsideWindow, FailureCause::SpamLimited, FailureCause::Unresolved]);

it('does nothing for an invitation of the other module', function (): void {
    event(waFailure($this->participant, $this->campaign, type: InvitationReference::ENGAGEMENT_INVITATION));

    Notification::assertNothingSent();
    expect($this->participant->refresh()->token_version)->toBe(0);
});

it('does not fall back when the person has no email, and says so', function (): void {
    Log::spy();
    $this->employee->update(['email' => null]);

    event(waFailure($this->participant, $this->campaign));

    Notification::assertNothingSent();
    expect($this->participant->refresh()->token_version)->toBe(0)->and($this->participant->invited_via)->toBe('whatsapp');
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'campaign.fallback_unavailable')->once();
});

it('does not fall back when the company turned its mail off', function (): void {
    Log::spy();
    TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('channel', 'mail')->update(['enabled' => false]);
    app()->forgetScopedInstances();

    event(waFailure($this->participant, $this->campaign));

    Notification::assertNothingSent();
    expect($this->participant->refresh()->token_version)->toBe(0);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'campaign.fallback_unavailable')->once();
});

it('does not fall back for an invitation that no longer waits', function (): void {
    Log::spy();
    $this->participant->instruments()->update(['status' => InstrumentStatus::Completed, 'completed_at' => now()]);

    event(waFailure($this->participant, $this->campaign));

    Notification::assertNothingSent();
    expect($this->participant->refresh()->token_version)->toBe(0);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'campaign.fallback_unavailable')->once();
});

it('rolls everything back when there is no mail quota left', function (): void {
    Log::spy();
    config(['billing.versions.v1.plans.cumplimiento.quotas.email_minimum' => 3, 'billing.versions.v1.plans.cumplimiento.quotas.email_per_seat' => 0]);
    resolve(Companies::class)->enter($this->tenant->id);
    resolve(MailQuota::class)->reserve(3);

    event(waFailure($this->participant, $this->campaign));

    Notification::assertNothingSent();
    expect($this->participant->refresh()->token_version)->toBe(0)
        ->and($this->participant->invited_via)->toBe('whatsapp')
        ->and($this->participant->token_hash)->toBe(Participant::hashToken($this->oldToken))
        ->and(mailReserved($this->tenant))->toBe(3);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'campaign.fallback_without_quota')->once();
});

it('is a queued listener with three tries and the design backoff', function (): void {
    $listener = new FallBackToMail;

    expect($listener)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and($listener->tries)->toBe(3)
        ->and($listener->backoff)->toBe([60, 300]);
});
