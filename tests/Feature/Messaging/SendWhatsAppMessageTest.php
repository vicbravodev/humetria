<?php

declare(strict_types=1);

use App\Modules\Messaging\Actions\ReconcileUnknown;
use App\Modules\Messaging\Contracts\InvitationLinks;
use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\SendOutcome;
use App\Modules\Messaging\Data\WhatsAppInvitation;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Events\WhatsAppMessageAccepted;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Messaging\Exceptions\RetryableWhatsAppFailure;
use App\Modules\Messaging\Jobs\SendWhatsAppMessage;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Models\Pause;
use App\Modules\Messaging\Support\DeliveryFailures;
use App\Modules\Messaging\Support\FakeWhatsAppGateway;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Messaging\DeliveryFactory;
use Database\Factories\Messaging\OptOutFactory;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

mutates(SendWhatsAppMessage::class, ReconcileUnknown::class, DeliveryFailures::class);

final class SendJobStubLinks implements InvitationLinks
{
    public static ?WhatsAppInvitation $answer = null;

    public function type(): string
    {
        return 'campaign_participant';
    }

    public function whatsApp(string $invitationId): ?WhatsAppInvitation
    {
        return self::$answer;
    }
}

beforeEach(function (): void {
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
    $this->tenant = Tenant::factory()->create();
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create();
    $this->gateway = new FakeWhatsAppGateway;
    app()->instance(WhatsAppGateway::class, $this->gateway);
    SendJobStubLinks::$answer = new WhatsAppInvitation('+528186875767', 'survey_invite_v1', ['Acme', '12 de mayo'], 'the-link-token');
    // The module's own links are tagged by its provider; the stub answers in their place (the container hands out the instance).
    app()->instance(App\Modules\Campaigns\Support\CampaignInvitationLinks::class, new SendJobStubLinks);
    Event::fake([WhatsAppMessageAccepted::class, WhatsAppMessageFailed::class]);
});

function pendingDelivery(Tenant $tenant, array $override = []): Delivery
{
    return DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'provider_message_id' => null, 'status' => DeliveryStatus::Created, ...$override]);
}

function runSend(Delivery $delivery): SendWhatsAppMessage
{
    app()->forgetScopedInstances();
    $job = new SendWhatsAppMessage($delivery->id);
    $job->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

it('sends the template, keeps the id Meta gave and announces the accepted message', function (): void {
    $delivery = pendingDelivery($this->tenant);
    Log::spy();

    runSend($delivery);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->provider_message_id)->toBe('wamid.fake-1')
        ->and($delivery->accepted_at)->not->toBeNull()
        ->and(DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'sent')->exists())->toBeTrue()
        ->and($this->gateway->sent())->toHaveCount(1)
        ->and($this->gateway->sent()[0]->to)->toBe('+528186875767')
        ->and($this->gateway->sent()[0]->template)->toBe('survey_invite_v1')
        ->and($this->gateway->sent()[0]->bodyParameters)->toBe(['Acme', '12 de mayo'])
        ->and($this->gateway->sent()[0]->buttonSuffix)->toBe('the-link-token')
        ->and($this->gateway->sent()[0]->callbackData)->toBe($delivery->id)
        ->and($this->gateway->sent()[0]->language)->toBe('es_MX')
        ->and(app(CurrentTenant::class)->id())->toBe($this->tenant->id);
    Event::assertDispatched(WhatsAppMessageAccepted::class, fn (WhatsAppMessageAccepted $e): bool => $e->deliveryId === $delivery->id && $e->tenantId === $this->tenant->id);
    Event::assertNotDispatched(WhatsAppMessageFailed::class);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'whatsapp.message_accepted')->once();
});

it('fails each definitive cause and says whether the mail takes over', function (FailureCause $cause, bool $fallsBack): void {
    $delivery = pendingDelivery($this->tenant);
    $this->gateway->script(SendOutcome::failed($cause));

    runSend($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and(DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'failed')->firstOrFail()->failure_cause)->toBe($cause);
    Event::assertDispatched(WhatsAppMessageFailed::class, fn (WhatsAppMessageFailed $e): bool => $e->cause === $cause && $e->fallsBackToMail() === $fallsBack
        && $e->deliveryId === $delivery->id && $e->invitation->id === $delivery->invitation_id && $e->invitation->sourceId === $delivery->source_id);
    Event::assertNotDispatched(WhatsAppMessageAccepted::class);
})->with([
    [FailureCause::NoWhatsappAccount, true],
    [FailureCause::TemplateNotApproved, true],
    [FailureCause::InvalidToken, true],
    [FailureCause::OutsideWindow, false],
    [FailureCause::SpamLimited, false],
    [FailureCause::EcosystemBlocked, false],
    [FailureCause::UnknownError, false],
]);

it('throws a retryable failure and leaves the message as it was', function (FailureCause $cause): void {
    $delivery = pendingDelivery($this->tenant);
    $this->gateway->script(SendOutcome::failed($cause));

    expect(fn () => runSend($delivery))->toThrow(RetryableWhatsAppFailure::class);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Created);
    Event::assertNothingDispatched();
})->with([FailureCause::RateLimited, FailureCause::ServerError]);

it('ends in retries_exhausted when the attempts run out, with the mail as the fallback', function (): void {
    $delivery = pendingDelivery($this->tenant);
    $job = new SendWhatsAppMessage($delivery->id);

    $job->failed(new RetryableWhatsAppFailure);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed);
    Event::assertDispatched(WhatsAppMessageFailed::class, fn (WhatsAppMessageFailed $e): bool => $e->cause === FailureCause::RetriesExhausted && $e->fallsBackToMail());
});

it('does not fail a message that was already sent when the job dies later', function (): void {
    $delivery = pendingDelivery($this->tenant, ['provider_message_id' => 'wamid.X', 'status' => DeliveryStatus::Sent]);

    (new SendWhatsAppMessage($delivery->id))->failed(new RuntimeException('late'));

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Sent);
    Event::assertNothingDispatched();
});

it('leaves a timeout as unknown: no retry, no release of the hold, no fallback', function (): void {
    $delivery = pendingDelivery($this->tenant);
    $this->gateway->script(SendOutcome::failed(FailureCause::TimedOut));

    $job = runSend($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Unknown)
        ->and($delivery->provider_message_id)->toBeNull();
    Event::assertNothingDispatched();
    $job->assertNotReleased();
});

it('sends nothing while paused by the operator, and says paused', function (): void {
    config(['whatsapp.paused' => true]);
    $delivery = pendingDelivery($this->tenant);

    runSend($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Paused)
        ->and($this->gateway->sent())->toBe([]);
    Event::assertDispatched(WhatsAppMessageFailed::class, fn (WhatsAppMessageFailed $e): bool => $e->cause === FailureCause::Paused && ! $e->fallsBackToMail());
});

it('opens a platform pause and sends nothing when the platform reached its monthly cap', function (): void {
    config(['whatsapp.platform_monthly_cap' => 2]);
    $other = Tenant::factory()->create();
    foreach (range(1, 2) as $n) {
        pendingDelivery($other, ['provider_message_id' => "wamid.{$n}", 'status' => DeliveryStatus::Sent, 'accepted_at' => now()]);
    }
    $delivery = pendingDelivery($this->tenant);

    runSend($delivery);

    expect(Pause::query()->whereNull('ended_at')->sole()->reason)->toBe('platform_cap')
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::Paused)
        ->and($this->gateway->sent())->toBe([]);
    Event::assertDispatched(WhatsAppMessageFailed::class, fn (WhatsAppMessageFailed $e): bool => $e->cause === FailureCause::Paused);
});

it('counts the platform cap from the first of the month in display time, not before', function (): void {
    config(['whatsapp.platform_monthly_cap' => 1]);
    CarbonImmutable::setTestNow('2026-11-18 15:00:00');
    pendingDelivery(Tenant::factory()->create(), ['provider_message_id' => 'wamid.old', 'status' => DeliveryStatus::Sent, 'accepted_at' => '2026-11-01 05:59:00']);
    $delivery = pendingDelivery($this->tenant);

    runSend($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Sent);
    CarbonImmutable::setTestNow();
});

it('waits for tomorrow when the company reached its daily cap, without spending an attempt', function (): void {
    config(['whatsapp.daily_cap_per_company' => 2]);
    CarbonImmutable::setTestNow('2026-11-18 15:00:00');
    foreach (range(1, 2) as $n) {
        pendingDelivery($this->tenant, ['provider_message_id' => "wamid.{$n}", 'status' => DeliveryStatus::Sent, 'accepted_at' => '2026-11-18 06:30:00']);
    }
    $delivery = pendingDelivery($this->tenant);
    Log::spy();

    $job = runSend($delivery);

    // 00:05 of the 19th in Mexico City is 06:05 UTC: 15 h 5 min after 15:00 UTC.
    $job->assertReleased(15 * 3600 + 5 * 60);
    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Created)
        ->and($this->gateway->sent())->toBe([]);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'whatsapp.daily_cap_reached')->once();
    CarbonImmutable::setTestNow();
});

it('does not count what another company sent, or what was accepted before today, against the daily cap', function (): void {
    config(['whatsapp.daily_cap_per_company' => 1]);
    CarbonImmutable::setTestNow('2026-11-18 15:00:00');
    pendingDelivery(Tenant::factory()->create(), ['provider_message_id' => 'wamid.a', 'status' => DeliveryStatus::Sent, 'accepted_at' => '2026-11-18 10:00:00']);
    pendingDelivery($this->tenant, ['provider_message_id' => 'wamid.b', 'status' => DeliveryStatus::Sent, 'accepted_at' => '2026-11-18 05:59:00']);
    $delivery = pendingDelivery($this->tenant);

    runSend($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Sent);
    CarbonImmutable::setTestNow();
});

it('sends once however many times it runs (idempotence)', function (): void {
    $delivery = pendingDelivery($this->tenant);

    runSend($delivery);
    runSend($delivery);

    expect($this->gateway->sent())->toHaveCount(1);
    Event::assertDispatchedTimes(WhatsAppMessageAccepted::class, 1);
});

it('does not send a message that already ended', function (DeliveryStatus $status): void {
    $delivery = pendingDelivery($this->tenant, ['status' => $status]);

    runSend($delivery);

    expect($this->gateway->sent())->toBe([]);
})->with([DeliveryStatus::Failed, DeliveryStatus::Unknown, DeliveryStatus::Paused, DeliveryStatus::Delivered]);

it('ignores a delivery that does not exist', function (): void {
    $job = new SendWhatsAppMessage((string) Illuminate\Support\Str::uuid());
    app()->call([$job, 'handle']);

    expect($this->gateway->sent())->toBe([]);
});

it('fails with invitation_closed, and no fallback, when the invitation no longer waits', function (): void {
    SendJobStubLinks::$answer = null;
    $delivery = pendingDelivery($this->tenant);

    runSend($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($this->gateway->sent())->toBe([]);
    Event::assertDispatched(WhatsAppMessageFailed::class, fn (WhatsAppMessageFailed $e): bool => $e->cause === FailureCause::InvitationClosed && ! $e->fallsBackToMail());
});

it('fails with opted_out, and the mail as fallback, for a STOP after the launch', function (): void {
    $delivery = pendingDelivery($this->tenant);
    ['hash' => $hash, 'version' => $version] = (new RecipientHash)->current('+528186875767');
    OptOutFactory::new()->create(['tenant_id' => $this->tenant->id, 'recipient_hash' => $hash, 'key_version' => $version]);

    runSend($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($this->gateway->sent())->toBe([]);
    Event::assertDispatched(WhatsAppMessageFailed::class, fn (WhatsAppMessageFailed $e): bool => $e->cause === FailureCause::OptedOut && $e->fallsBackToMail());
});

it('keeps the token and the phone out of the queue payload', function (): void {
    $payload = serialize(new SendWhatsAppMessage('delivery-id'));

    expect($payload)->not->toContain('the-link-token')->not->toContain('8186875767')->toContain('delivery-id');
});

it('is configured as the design says', function (): void {
    $job = new SendWhatsAppMessage('x');

    expect($job->queue)->toBe('whatsapp')
        ->and($job->maxExceptions)->toBe(3)
        ->and($job->backoff())->toBe([60, 300])
        ->and($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class)
        ->and($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addDays(3)->subMinute()->getTimestamp())
        ->and($job->retryUntil()->getTimestamp())->toBeLessThan(now()->addDays(3)->addMinute()->getTimestamp());
});

it('limits the sends per second with the configured number', function (): void {
    config(['whatsapp.max_per_second' => 7]);

    $limit = Illuminate\Support\Facades\RateLimiter::limiter('whatsapp-send')(new stdClass);

    expect($limit->maxAttempts)->toBe(7)->and($limit->decaySeconds)->toBe(1);
});

it('settles an unknown of more than 24 hours as used, without fallback, and leaves a younger one', function (): void {
    $old = pendingDelivery($this->tenant, ['status' => DeliveryStatus::Unknown]);
    $young = pendingDelivery($this->tenant, ['status' => DeliveryStatus::Unknown]);
    Delivery::query()->whereKey($old->id)->toBase()->update(['updated_at' => now()->subHours(25)]);
    Delivery::query()->whereKey($young->id)->toBase()->update(['updated_at' => now()->subHours(23)]);

    $resolved = app(ReconcileUnknown::class)->handle();

    expect($resolved)->toBe(1)
        ->and($old->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and(DeliveryEvent::query()->where('delivery_id', $old->id)->where('status', 'failed')->firstOrFail()->failure_cause)->toBe(FailureCause::Unresolved)
        ->and($young->refresh()->status)->toBe(DeliveryStatus::Unknown);
    Event::assertDispatched(WhatsAppMessageAccepted::class, fn (WhatsAppMessageAccepted $e): bool => $e->deliveryId === $old->id && $e->tenantId === $this->tenant->id);
    Event::assertNotDispatched(WhatsAppMessageFailed::class);
});

it('does not send again when Meta accepted and the save failed: the message waits as unknown for its status (rule 8)', function (): void {
    $delivery = pendingDelivery($this->tenant);
    $armed = true;
    Event::listen('eloquent.creating: '.DeliveryEvent::class, function () use (&$armed): void {
        if ($armed) {
            $armed = false;

            throw new RuntimeException('connection lost');
        }
    });

    expect(fn () => runSend($delivery))->toThrow(RuntimeException::class);

    expect($this->gateway->sent())->toHaveCount(1)
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::Unknown)
        ->and($delivery->provider_message_id)->toBeNull();

    // The retry of the queue finds it in transit and sends nothing.
    runSend($delivery);

    expect($this->gateway->sent())->toHaveCount(1);
    Event::assertNotDispatched(WhatsAppMessageFailed::class);
});

it('marks the message as in transit while Meta is called, and gives it back to created when the cause is worth another attempt', function (): void {
    $delivery = pendingDelivery($this->tenant);
    $seen = null;
    $this->gateway->script(SendOutcome::failed(FailureCause::ServerError));
    app()->instance(WhatsAppGateway::class, new class($delivery, $seen) implements WhatsAppGateway
    {
        public function __construct(private Delivery $delivery, public ?DeliveryStatus &$seen) {}

        public function send(OutboundMessage $message): SendOutcome
        {
            $this->seen = Delivery::query()->findOrFail($this->delivery->id)->status;

            return SendOutcome::failed(FailureCause::ServerError);
        }

        public function sendText(string $to, string $body): SendOutcome
        {
            return SendOutcome::failed(FailureCause::ServerError);
        }
    });

    expect(fn () => runSend($delivery))->toThrow(RetryableWhatsAppFailure::class);

    expect($seen)->toBe(DeliveryStatus::Unknown)->and($delivery->refresh()->status)->toBe(DeliveryStatus::Created);
});

it('counts a message settled as used against the daily cap and the platform cap: it gets its acceptance time', function (): void {
    config(['whatsapp.daily_cap_per_company' => 1, 'whatsapp.platform_monthly_cap' => 1_000]);
    $stuck = pendingDelivery($this->tenant, ['status' => DeliveryStatus::Unknown]);
    Delivery::query()->whereKey($stuck->id)->toBase()->update(['updated_at' => now()->subHours(25)]);

    app(ReconcileUnknown::class)->handle();

    expect($stuck->refresh()->accepted_at)->not->toBeNull()
        ->and($stuck->provider_message_id)->toBeNull();

    $next = pendingDelivery($this->tenant);
    $job = runSend($next);

    expect($this->gateway->sent())->toBe([]);
    $job->assertReleased();
    expect($next->refresh()->status)->toBe(DeliveryStatus::Created);
});
