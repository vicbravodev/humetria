<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Engagement\Actions\LaunchSurvey;
use App\Modules\Engagement\Actions\OpenInvitation;
use App\Modules\Engagement\Exceptions\SurveyUnavailable;
use App\Modules\Engagement\Listeners\FallBackToMail;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantChannel;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

mutates(FallBackToMail::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    Notification::fake();
    RateLimiter::clear('survey-invitations');
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')],
        'billing.versions.v1.plans.cumplimiento.quotas.whatsapp' => 100]);
    [, $account] = paidCompany();
    $this->tenant = planFor($account, tenant: Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']));
    workIn($this->tenant);
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);
    TenantChannelFactory::new()->for($this->tenant)->create(['channel' => 'mail', 'preference' => 2]);

    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    Employee::factory()->count(6)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id])->values()
        ->each(fn (Employee $employee, int $i) => $employee->update(['mobile' => '+52818687'.str_pad((string) (5700 + $i), 4, '0', STR_PAD_LEFT), 'email' => "persona{$i}@example.test"]));
    $this->survey = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
    // The queue is sync: the batch of invitations runs as the launch commits, and WhatsApp is the channel of everyone.
    resolve(LaunchSurvey::class)->handle($this->survey->id, new CarbonImmutable('2026-11-20'), User::factory()->create()->id);
    Notification::fake();
    $this->invitation = Invitation::query()->where('survey_id', $this->survey->id)->orderBy('id')->firstOrFail();
    $this->employee = Employee::query()->findOrFail($this->invitation->employee_id);
    $this->oldToken = $this->invitation->surveyToken();
});

function surveyWaFailure(Invitation $invitation, Survey $survey, FailureCause $cause = FailureCause::NoWhatsappAccount, ?string $type = null): WhatsAppMessageFailed
{
    return new WhatsAppMessageFailed(
        (string) Str::uuid(),
        $invitation->tenant_id,
        CarbonImmutable::now(),
        new InvitationReference($type ?? InvitationReference::ENGAGEMENT_INVITATION, $invitation->id, InvitationReference::ENGAGEMENT_SURVEY, $survey->id),
        $cause,
    );
}

it('rotates the link and mails the person when the number has no WhatsApp (CUJ 3)', function (): void {
    expect($this->invitation->invited_via)->toBe('whatsapp');

    event(surveyWaFailure($this->invitation, $this->survey));

    $invitation = $this->invitation->refresh();
    $newToken = $invitation->surveyToken();
    expect($newToken)->not->toBe($this->oldToken)
        ->and($invitation->invited_via)->toBe('mail')
        ->and($invitation->token_version)->toBe(1)
        ->and($invitation->token_hash)->toBe(Invitation::hashToken($newToken))
        ->and(fn () => resolve(OpenInvitation::class)->handle($this->oldToken))->toThrow(SurveyUnavailable::class)
        ->and(resolve(OpenInvitation::class)->handle($newToken)->invitation->id)->toBe($invitation->id);
    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 1);
    Notification::assertSentOnDemand(SurveyInvitation::class, fn (SurveyInvitation $mail, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail']
        && $notifiable->routes === ['mail' => $this->employee->email]
        && str_contains((string) $mail->toMail($notifiable)->actionUrl, $newToken));
});

it('does not put the phone in the logs', function (): void {
    Log::spy();

    event(surveyWaFailure($this->invitation, $this->survey));

    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => ! str_contains((string) json_encode($context), '8186875'));
});

it('does nothing for a cause the mail does not take over from, or for an invitation of the other module', function (): void {
    foreach ([FailureCause::Paused, FailureCause::InvitationClosed, FailureCause::SpamLimited] as $cause) {
        event(surveyWaFailure($this->invitation, $this->survey, $cause));
    }
    event(surveyWaFailure($this->invitation, $this->survey, type: InvitationReference::CAMPAIGN_PARTICIPANT));

    Notification::assertNothingSent();
    expect($this->invitation->refresh()->token_version)->toBe(0)->and($this->invitation->invited_via)->toBe('whatsapp');
});

it('does not fall back when the person has no email, the company turned its mail off, or the invitation no longer waits', function (string $case): void {
    Log::spy();
    match ($case) {
        'no email' => $this->employee->update(['email' => null]),
        'mail off' => TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('channel', 'mail')->update(['enabled' => false]),
        'answered' => $this->invitation->update(['answered' => true]),
        'cancelled' => $this->invitation->update(['cancelled' => true]),
    };
    app()->forgetScopedInstances();

    event(surveyWaFailure($this->invitation, $this->survey));

    Notification::assertNothingSent();
    expect($this->invitation->refresh()->token_version)->toBe(0)->and($this->invitation->invited_via)->toBe('whatsapp');
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'engagement.fallback_unavailable')->once();
})->with(['no email', 'mail off', 'answered', 'cancelled']);

it('rolls everything back when there is no mail quota left', function (): void {
    Log::spy();
    config(['billing.versions.v1.plans.cumplimiento.quotas.email_minimum' => 3, 'billing.versions.v1.plans.cumplimiento.quotas.email_per_seat' => 0]);
    resolve(Companies::class)->enter($this->tenant->id);
    resolve(MailQuota::class)->reserve(3);

    event(surveyWaFailure($this->invitation, $this->survey));

    Notification::assertNothingSent();
    expect($this->invitation->refresh()->token_version)->toBe(0)
        ->and($this->invitation->invited_via)->toBe('whatsapp')
        ->and($this->invitation->token_hash)->toBe(Invitation::hashToken($this->oldToken))
        ->and((int) UsageCounter::query()->where('tenant_id', $this->tenant->id)->where('meter', 'email_campaign')->sum('reserved'))->toBe(3);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'engagement.fallback_without_quota')->once();
});

it('reuses the rotated link and the hold when the queue retries it: one rotation, one hold, the same link in the mail', function (): void {
    $reserved = fn (): int => (int) UsageCounter::query()->where('tenant_id', $this->tenant->id)->where('meter', 'email_campaign')->sum('reserved');
    $before = $reserved();
    $event = surveyWaFailure($this->invitation, $this->survey);

    (new FallBackToMail)->handle($event);
    $link = $this->invitation->refresh()->surveyToken();
    (new FallBackToMail)->handle($event);

    expect($this->invitation->refresh()->token_version)->toBe(1)
        ->and($this->invitation->surveyToken())->toBe($link)
        ->and($reserved())->toBe($before + 1);
});

it('leaves the person as failed, for RH to resend, when the mail never goes out and the tries run out', function (): void {
    Log::spy();
    event(surveyWaFailure($this->invitation, $this->survey));
    $this->invitation->update(['invitation_status' => App\Modules\Engagement\Enums\InvitationStatus::Sent]);

    (new FallBackToMail)->failed(surveyWaFailure($this->invitation, $this->survey), new RuntimeException('smtp down'));

    expect($this->invitation->refresh()->invitation_status)->toBe(App\Modules\Engagement\Enums\InvitationStatus::Failed);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $event, array $context = []): bool => $event === 'engagement.fallback_failed' && $context['exception'] === RuntimeException::class)->once();
});

it('is a queued listener with three tries and the design backoff', function (): void {
    $listener = new FallBackToMail;

    expect($listener)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and($listener->tries)->toBe(3)
        ->and($listener->backoff)->toBe([60, 300]);
});
