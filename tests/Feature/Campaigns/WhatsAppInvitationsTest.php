<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Messaging\Contracts\InvitationLinks;
use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Jobs\SendWhatsAppMessage;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\FakeWhatsAppGateway;
use App\Modules\Messaging\Support\TemplateRegistry;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Bus\PendingBatch;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Notification::fake();
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')],
        'billing.versions.v2.plans.cumplimiento.quotas.whatsapp' => 100]);
    [, $account] = paidCompany();
    $this->tenant = planFor($account, tenant: Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    // WhatsApp first, mail second.
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);
    TenantChannelFactory::new()->for($this->tenant)->create(['channel' => 'mail', 'preference' => 2]);
});

/** A launched evaluation of `$people` people, each with their own phone and email; the jobs of the batch are returned. */
function whatsAppLaunch(Tenant $tenant, WorkCenter $plant, User $hr, int $people, ?Closure $prepare = null): array
{
    $staff = staffOf($tenant, $plant, $people)->values();
    $staff->each(fn ($employee, int $i) => $employee->update(['mobile' => '+52818687'.str_pad((string) (5700 + $i), 4, '0', STR_PAD_LEFT), 'email' => "persona{$i}@example.test"]));
    $prepare !== null && $prepare($staff);
    test()->actingAs($hr)->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
    Bus::fake();
    $response = test()->actingAs($hr)->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => '2026-11-20']);

    return [$campaign->refresh(), $response, $staff];
}

function whatsAppBatch(): array
{
    $jobs = [];
    Bus::assertBatched(function (PendingBatch $batch) use (&$jobs): bool {
        $jobs = $batch->jobs->all();

        return true;
    });

    return $jobs;
}

function whatsAppCounterOf(Tenant $tenant): ?UsageCounter
{
    return UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', 'whatsapp')->first();
}

/** @return array<string, list<string>> the channel(s) of each person's invitation, by the address it went to */
function channelsSent(): array
{
    $sent = [];
    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $mail, array $channels, AnonymousNotifiable $notifiable) use (&$sent): bool {
        $sent[(string) ($notifiable->routes['mail'] ?? $notifiable->routes['whatsapp'])] = $channels;

        return true;
    });

    return $sent;
}

it('reserves all the WhatsApp messages of a launch or none (CUJ 2)', function (): void {
    config(['billing.versions.v2.plans.cumplimiento.quotas.whatsapp' => 5]);

    [$campaign, $response] = whatsAppLaunch($this->tenant, $this->plant, $this->hr, 6);

    $response->assertRedirect();
    expect(Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->whereNotNull('invitation_status')->count())->toBe(0)
        ->and(whatsAppCounterOf($this->tenant)?->reserved ?? 0)->toBe(0);
    Bus::assertNothingBatched();
});

it('holds exactly one message for each person it will write to through WhatsApp', function (): void {
    config(['billing.versions.v2.plans.cumplimiento.quotas.whatsapp' => 6]);

    whatsAppLaunch($this->tenant, $this->plant, $this->hr, 6);

    expect(whatsAppCounterOf($this->tenant)?->reserved)->toBe(6);
});

it('writes to each person through one channel only and keeps it in invited_via', function (): void {
    [$campaign, , $staff] = whatsAppLaunch($this->tenant, $this->plant, $this->hr, 6, function ($staff): void {
        $staff[4]->update(['mobile' => null]);
        $staff[5]->update(['mobile' => null, 'email' => null]);
    });
    foreach (whatsAppBatch() as $job) {
        $job->handle();
    }

    $participants = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->get()->keyBy('employee_id');
    expect($participants[$staff[0]->id]->invited_via)->toBe('whatsapp')
        ->and($participants[$staff[3]->id]->invited_via)->toBe('whatsapp')
        ->and($participants[$staff[4]->id]->invited_via)->toBe('mail')
        ->and($participants[$staff[5]->id]->invited_via)->toBeNull()
        ->and($participants[$staff[5]->id]->invitation_status)->toBe(InvitationStatus::Unreachable)
        ->and($participants[$staff[0]->id]->invitation_status)->toBe(InvitationStatus::Sent);
    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 5);
    expect(array_count_values(array_map(fn (array $channels): string => implode(',', $channels), channelsSent())))->toEqual(['whatsapp' => 4, 'mail' => 1]);
});

it('leaves a person with no mobile unreachable when WhatsApp is the only channel on (rule 4)', function (): void {
    App\Modules\Tenancy\Models\TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('channel', 'mail')->update(['enabled' => false]);

    [$campaign, , $staff] = whatsAppLaunch($this->tenant, $this->plant, $this->hr, 6, fn ($staff) => $staff[5]->update(['mobile' => null]));

    $participants = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->get()->keyBy('employee_id');
    expect($participants[$staff[5]->id]->invitation_status)->toBe(InvitationStatus::Unreachable)
        ->and($participants[$staff[0]->id]->invitation_status)->toBe(InvitationStatus::Queued);
});

it('never sends a reminder through WhatsApp: mail if the person has it, and a person with only WhatsApp keeps their link', function (): void {
    Log::spy();
    [$campaign, , $staff] = whatsAppLaunch($this->tenant, $this->plant, $this->hr, 6, fn ($staff) => $staff[5]->update(['email' => null]));
    Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->get()->each(fn (Participant $p) => $p->update(['invited_at' => now()->subDay(), 'token_hash' => hash('sha256', $p->id)]));
    foreach (whatsAppBatch() as $job) {
        $job->handle();
    }

    $participants = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->get()->keyBy('employee_id');
    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 5);
    expect(array_values(array_unique(array_map(fn (array $channels): string => implode(',', $channels), channelsSent()))))->toBe(['mail'])
        ->and($participants[$staff[5]->id]->invitation_status)->toBe(InvitationStatus::Sent)
        ->and($participants[$staff[5]->id]->invited_via)->toBeNull();
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => $event === 'campaign.invitations_sent' && $context['skipped_reminders'] === 1);
});

it('keeps everything on mail while the function is off, whatever the company rows say', function (): void {
    config(['whatsapp.enabled' => false]);

    [$campaign] = whatsAppLaunch($this->tenant, $this->plant, $this->hr, 6);
    foreach (whatsAppBatch() as $job) {
        $job->handle();
    }

    expect(Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->pluck('invited_via')->unique()->all())->toBe(['mail'])
        ->and(whatsAppCounterOf($this->tenant))->toBeNull();
    expect(array_values(array_unique(array_map(fn (array $channels): string => implode(',', $channels), channelsSent()))))->toBe(['mail']);
});

it('names its invitation so the delivery can be followed', function (): void {
    [$campaign] = whatsAppLaunch($this->tenant, $this->plant, $this->hr, 6);
    foreach (whatsAppBatch() as $job) {
        $job->handle();
    }
    $ids = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->pluck('id')->all();

    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $mail) use ($ids, $campaign): bool {
        $reference = $mail->deliveryReference();

        return $reference !== null && $reference->type === InvitationReference::CAMPAIGN_PARTICIPANT && in_array($reference->id, $ids, true)
            && $reference->sourceType === InvitationReference::CAMPAIGN && $reference->sourceId === $campaign->id;
    });
});

it('tells Messaging, when it runs, the phone, the template, the two parameters and the link, and nothing once the invitation no longer waits', function (): void {
    [$campaign] = whatsAppLaunch($this->tenant, $this->plant, $this->hr, 6);
    $participant = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->firstOrFail();
    $links = collect(app()->tagged(InvitationLinks::TAG))->first(fn (InvitationLinks $links): bool => $links->type() === InvitationReference::CAMPAIGN_PARTICIPANT);
    $mobile = App\Modules\Organization\Models\Employee::query()->withoutGlobalScopes()->findOrFail($participant->employee_id)->mobile;
    resolve(App\Modules\Tenancy\Contracts\Companies::class)->enter($this->tenant->id);

    $invitation = $links->whatsApp($participant->id);

    expect($invitation?->mobile)->toBe($mobile)
        ->and($invitation->template)->toBe('survey_invite_v1')
        ->and($invitation->bodyParameters)->toBe(['Autopartes del Bajío', '20 de noviembre'])
        ->and($invitation->buttonSuffix)->toBe($participant->surveyToken())
        ->and(count($invitation->bodyParameters))->toBe((new TemplateRegistry)->get($invitation->template)->bodyParameterCount);

    $participant->instruments()->update(['status' => InstrumentStatus::Completed, 'completed_at' => now()]);
    expect($links->whatsApp($participant->id))->toBeNull();
    $participant->instruments()->update(['status' => InstrumentStatus::Pending, 'completed_at' => null]);
    expect($links->whatsApp($participant->id))->not->toBeNull();
    $campaign->update(['status' => App\Modules\Campaigns\Enums\CampaignStatus::Closed, 'closed_at' => now()]);
    expect($links->whatsApp($participant->id))->toBeNull()
        ->and($links->whatsApp((string) Illuminate\Support\Str::uuid()))->toBeNull();
});

it('goes all the way to the gateway with the real invitation: the phone, the parameters and the participant\'s link', function (): void {
    [$campaign] = whatsAppLaunch($this->tenant, $this->plant, $this->hr, 6);
    app()->forgetInstance(Illuminate\Notifications\ChannelManager::class);
    Notification::clearResolvedInstance(Illuminate\Notifications\ChannelManager::class);
    Notification::extend('whatsapp', fn ($app) => $app->make(App\Modules\Messaging\Support\WhatsAppChannel::class));
    $gateway = new FakeWhatsAppGateway;
    app()->instance(WhatsAppGateway::class, $gateway);
    foreach (whatsAppBatch() as $job) {
        $job->handle();
    }

    Bus::assertDispatchedTimes(SendWhatsAppMessage::class, 6);
    $delivery = Delivery::query()->where('source_id', $campaign->id)->firstOrFail();
    expect(Delivery::query()->where('source_id', $campaign->id)->where('channel', 'whatsapp')->count())->toBe(6);

    (new SendWhatsAppMessage($delivery->id))->handle(...array_map(fn (string $class): object => resolve($class), [
        WhatsAppGateway::class, App\Modules\Messaging\Contracts\ChannelConsent::class, App\Modules\Messaging\Support\WhatsAppSwitch::class,
        App\Modules\Tenancy\Contracts\Companies::class, App\Modules\Messaging\Support\DeliveryFailures::class, App\Modules\Messaging\Support\RecordDeliveryEvent::class,
    ]));

    $participant = Participant::query()->withoutGlobalScopes()->findOrFail($delivery->invitation_id);
    expect($gateway->sent())->toHaveCount(1)
        ->and($gateway->sent()[0]->buttonSuffix)->toBe($participant->surveyToken())
        ->and($gateway->sent()[0]->bodyParameters)->toBe(['Autopartes del Bajío', '20 de noviembre'])
        ->and($gateway->sent()[0]->callbackData)->toBe($delivery->id);
});
