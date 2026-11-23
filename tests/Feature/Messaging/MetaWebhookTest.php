<?php

declare(strict_types=1);

use App\Modules\Messaging\Actions\HandleMetaEvents;
use App\Modules\Messaging\Actions\ReplayOrphanEvents;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Events\WhatsAppMessageAccepted;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Messaging\Http\Controllers\MetaWebhookController;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Models\WebhookEvent;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Messaging\DeliveryFactory;
use Database\Factories\Messaging\WebhookEventFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

mutates(MetaWebhookController::class, HandleMetaEvents::class, ReplayOrphanEvents::class);

beforeEach(function (): void {
    config(['whatsapp.app_secrets' => [META_SECRET], 'whatsapp.verify_token' => 'the-verify-token', 'messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
    Event::fake([WhatsAppMessageAccepted::class, WhatsAppMessageFailed::class]);
    $this->tenant = Tenant::factory()->create();
});

/** @return array<string, mixed> */
function statusPayload(string $wamid, string $status, ?string $callback = null, ?int $timestamp = null, ?int $code = null): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => [['id' => '1', 'changes' => [['field' => 'messages', 'value' => [
        'messaging_product' => 'whatsapp',
        'statuses' => [array_filter([
            'id' => $wamid,
            'status' => $status,
            'timestamp' => (string) ($timestamp ?? 1_795_000_000),
            'recipient_id' => '5218186875767',
            'biz_opaque_callback_data' => $callback,
            'errors' => $code === null ? null : [['code' => $code, 'title' => 'x']],
        ], fn ($v): bool => $v !== null)],
    ]]]]]];
}

function sentDelivery(Tenant $tenant, string $wamid = 'wamid.A', DeliveryStatus $status = DeliveryStatus::Sent, array $over = []): Delivery
{
    return DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'provider_message_id' => $wamid, 'status' => $status, 'accepted_at' => now(), ...$over]);
}

it('answers the verification with the challenge when the token is right', function (): void {
    $response = $this->get(route('messaging.webhooks.meta.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'the-verify-token', 'hub_challenge' => '1158201444']));

    $response->assertOk();
    expect($response->getContent())->toBe('1158201444')->and($response->headers->get('Content-Type'))->toContain('text/plain');
});

it('refuses the verification with a wrong token, another mode, no token, or no token configured', function (array $query, ?string $configured): void {
    config(['whatsapp.verify_token' => $configured]);

    $this->get(route('messaging.webhooks.meta.verify', $query))->assertForbidden();
})->with([
    'wrong token' => [['hub_mode' => 'subscribe', 'hub_verify_token' => 'nope', 'hub_challenge' => '1'], 'the-verify-token'],
    'another mode' => [['hub_mode' => 'unsubscribe', 'hub_verify_token' => 'the-verify-token', 'hub_challenge' => '1'], 'the-verify-token'],
    'no token sent' => [['hub_mode' => 'subscribe', 'hub_challenge' => '1'], 'the-verify-token'],
    'nothing configured, nothing sent' => [['hub_mode' => 'subscribe', 'hub_challenge' => '1'], null],
    'nothing configured, empty sent' => [['hub_mode' => 'subscribe', 'hub_verify_token' => '', 'hub_challenge' => '1'], null],
]);

it('processes a POST with a valid signature and answers an empty 200', function (): void {
    $delivery = sentDelivery($this->tenant);

    $response = postMeta(statusPayload('wamid.A', 'delivered'));

    expect($response->getStatusCode())->toBe(200)->and($response->getContent())->toBe('')
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered);
});

it('rejects a missing, altered or wrong-secret signature with 403 and processes nothing', function (string $case): void {
    $delivery = sentDelivery($this->tenant);
    $payload = statusPayload('wamid.A', 'delivered');

    $response = match ($case) {
        'missing' => postMeta($payload, null),
        'altered header' => postMeta($payload, header: 'sha256='.str_repeat('0', 64)),
        'wrong secret' => postMeta($payload, 'another-secret'),
        'body changed after signing' => postMeta(json_encode(statusPayload('wamid.A', 'read'), JSON_THROW_ON_ERROR), header: 'sha256='.hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), META_SECRET)),
    };

    expect($response->getStatusCode())->toBe(403)
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::Sent)
        ->and(WebhookEvent::query()->count())->toBe(0);
})->with(['missing', 'altered header', 'wrong secret', 'body changed after signing']);

it('accepts the previous secret during a rotation', function (): void {
    config(['whatsapp.app_secrets' => ['new-secret', META_SECRET]]);
    $delivery = sentDelivery($this->tenant);

    expect(postMeta(statusPayload('wamid.A', 'delivered'))->getStatusCode())->toBe(200)
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered);
});

it('answers 400 and logs it, without the body, to a signed body that is not a JSON object', function (string $raw): void {
    Log::spy();

    expect(postMeta($raw)->getStatusCode())->toBe(400);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $event, array $context = []): bool => $event === 'whatsapp.webhook_malformed' && ! str_contains((string) json_encode($context), 'garbage'))->once();
})->with(['not json garbage', '"a string garbage"', '42', '']);

it('does not process the same status twice', function (): void {
    $delivery = sentDelivery($this->tenant);
    $payload = statusPayload('wamid.A', 'delivered');

    postMeta($payload);
    postMeta($payload);

    expect(DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'delivered')->count())->toBe(1)
        ->and(WebhookEvent::query()->where('provider', 'meta')->count())->toBe(1);
});

it('keeps the whole flow, with the exact time of each status, and never goes back', function (): void {
    $delivery = sentDelivery($this->tenant, status: DeliveryStatus::Created, over: ['provider_message_id' => 'wamid.A']);

    postMeta(statusPayload('wamid.A', 'delivered', timestamp: 1_795_000_100));
    postMeta(statusPayload('wamid.A', 'sent', timestamp: 1_795_000_000));
    postMeta(statusPayload('wamid.A', 'read', timestamp: 1_795_000_200));

    $events = DeliveryEvent::query()->where('delivery_id', $delivery->id)->orderBy('occurred_at')->get();
    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Read)
        ->and($events->pluck('status')->map->value->all())->toBe(['sent', 'delivered', 'read'])
        ->and($events->first()?->occurred_at->getTimestamp())->toBe(1_795_000_000);
});

it('ignores a field it does not know, a status it does not know, and an empty payload, with a 200', function (array $payload): void {
    $delivery = sentDelivery($this->tenant);

    expect(postMeta($payload)->getStatusCode())->toBe(200)
        ->and(DeliveryEvent::query()->where('delivery_id', $delivery->id)->count())->toBe(0)
        ->and(WebhookEvent::query()->count())->toBe(0);
})->with([
    'unknown field' => [['entry' => [['changes' => [['field' => 'account_alerts', 'value' => ['x' => 1]]]]]]],
    'unknown status' => [statusPayload('wamid.A', 'deleted')],
    'no entry' => [['object' => 'whatsapp_business_account']],
    'malformed entry' => [['entry' => 'nope']],
    'malformed status' => [['entry' => [['changes' => [['field' => 'messages', 'value' => ['statuses' => ['nope', ['status' => 'sent']]]]]]]]],
]);

it('writes the status in the company of the message, whoever is current', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    $delivery = sentDelivery($this->tenant);

    postMeta(statusPayload('wamid.A', 'delivered'));

    $event = DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'delivered')->sole();
    expect($event->tenant_id)->toBe($this->tenant->id)
        ->and(resolve(CurrentTenant::class)->id())->toBe($this->tenant->id);
});

it('maps a failed status by its error code and tells whoever listens, once', function (): void {
    $delivery = sentDelivery($this->tenant);
    $payload = statusPayload('wamid.A', 'failed', code: 131048);

    postMeta($payload);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and(DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'failed')->sole()->failure_cause)->toBe(FailureCause::SpamLimited);
    Event::assertDispatchedTimes(WhatsAppMessageFailed::class, 1);
    Event::assertDispatched(WhatsAppMessageFailed::class, fn (WhatsAppMessageFailed $e): bool => $e->deliveryId === $delivery->id && $e->cause === FailureCause::SpamLimited && ! $e->fallsBackToMail());
});

it('does not announce a failure for a message that already ended as failed', function (): void {
    sentDelivery($this->tenant, status: DeliveryStatus::Failed);

    postMeta(statusPayload('wamid.A', 'failed', code: 131026));

    Event::assertNotDispatched(WhatsAppMessageFailed::class);
});

it('uses an unknown error for a failed status with no code', function (): void {
    $delivery = sentDelivery($this->tenant);

    postMeta(statusPayload('wamid.A', 'failed'));

    expect(DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'failed')->sole()->failure_cause)->toBe(FailureCause::UnknownError);
});

it('does not announce the acceptance of a message the job already announced', function (): void {
    sentDelivery($this->tenant);

    postMeta(statusPayload('wamid.A', 'delivered'));

    Event::assertNotDispatched(WhatsAppMessageAccepted::class);
});

it('settles an unknown message by the id it carried: keeps the wamid, announces the acceptance once', function (): void {
    $delivery = DeliveryFactory::new()->create(['tenant_id' => $this->tenant->id, 'provider_message_id' => null, 'status' => DeliveryStatus::Unknown, 'accepted_at' => null]);

    postMeta(statusPayload('wamid.NEW', 'delivered', $delivery->id));
    postMeta(statusPayload('wamid.NEW', 'read', $delivery->id));

    $delivery->refresh();
    expect($delivery->provider_message_id)->toBe('wamid.NEW')
        ->and($delivery->accepted_at)->not->toBeNull()
        ->and($delivery->status)->toBe(DeliveryStatus::Read);
    Event::assertDispatchedTimes(WhatsAppMessageAccepted::class, 1);
    Event::assertDispatched(WhatsAppMessageAccepted::class, fn (WhatsAppMessageAccepted $e): bool => $e->deliveryId === $delivery->id && $e->tenantId === $this->tenant->id);
});

it('settles an unknown message that Meta says failed with 131026 and falls back to mail', function (): void {
    $delivery = DeliveryFactory::new()->create(['tenant_id' => $this->tenant->id, 'provider_message_id' => null, 'status' => DeliveryStatus::Unknown, 'accepted_at' => null]);

    postMeta(statusPayload('wamid.NEW', 'failed', $delivery->id, code: 131026));

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed);
    Event::assertNotDispatched(WhatsAppMessageAccepted::class);
    Event::assertDispatched(WhatsAppMessageFailed::class, fn (WhatsAppMessageFailed $e): bool => $e->cause === FailureCause::NoWhatsappAccount && $e->fallsBackToMail());
});

it('ignores a callback id that is not a uuid', function (): void {
    sentDelivery($this->tenant);

    expect(postMeta(statusPayload('wamid.UNKNOWN', 'sent', 'not-a-uuid'))->getStatusCode())->toBe(200);
});

it('keeps the status of a wamid it does not know as an orphan, with no phone, and reprocesses it when the message appears', function (): void {
    postMeta(statusPayload('wamid.LATE', 'delivered', timestamp: 1_795_000_300));

    $orphan = WebhookEvent::query()->whereNotNull('orphan')->sole();
    expect($orphan->orphan)->toEqualCanonicalizing(['provider_message_id' => 'wamid.LATE', 'callback_id' => null, 'status' => 'delivered', 'timestamp' => 1_795_000_300, 'error_code' => null])
        ->and(json_encode($orphan->getAttributes(), JSON_THROW_ON_ERROR))->not->toContain('8186875767');

    expect(app(ReplayOrphanEvents::class)->handle())->toBe(0);

    $delivery = sentDelivery($this->tenant, 'wamid.LATE');
    app(ReplayOrphanEvents::class)->handle();

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered)
        ->and($orphan->refresh()->processed_at)->not->toBeNull();

    app(ReplayOrphanEvents::class)->handle();
    expect(DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'delivered')->count())->toBe(1);
});

it('reprocesses an orphan whose message is found by the id it carried', function (): void {
    $delivery = DeliveryFactory::new()->create(['tenant_id' => $this->tenant->id, 'provider_message_id' => null, 'status' => DeliveryStatus::Created]);
    WebhookEventFactory::new()->create(['provider' => 'meta', 'event_id' => 'status:wamid.Z:sent:1', 'orphan' => ['provider_message_id' => 'wamid.Z', 'callback_id' => $delivery->id, 'status' => 'sent', 'timestamp' => 1_795_000_000, 'error_code' => null], 'received_at' => now()]);

    app(ReplayOrphanEvents::class)->handle();

    expect($delivery->refresh()->provider_message_id)->toBe('wamid.Z')->and($delivery->status)->toBe(DeliveryStatus::Sent);
    Event::assertDispatched(WhatsAppMessageAccepted::class);
});

it('discards the orphans older than the window and says how many', function (): void {
    config(['messaging.orphan_minutes' => 60]);
    Log::spy();
    $old = WebhookEventFactory::new()->create(['provider' => 'meta', 'orphan' => ['provider_message_id' => 'wamid.OLD', 'callback_id' => null, 'status' => 'sent', 'timestamp' => 1, 'error_code' => null], 'received_at' => now()->subMinutes(61)]);
    $young = WebhookEventFactory::new()->create(['provider' => 'meta', 'orphan' => ['provider_message_id' => 'wamid.YOUNG', 'callback_id' => null, 'status' => 'sent', 'timestamp' => 1, 'error_code' => null], 'received_at' => now()->subMinutes(59)]);

    app(ReplayOrphanEvents::class)->handle();

    expect($old->refresh()->processed_at)->not->toBeNull()->and($young->refresh()->processed_at)->toBeNull();
    Log::shouldHaveReceived('info')->with('whatsapp.orphans_discarded', ['discarded' => 1])->once();
});

it('logs a change of the number quality as a warning, with only the event and the limit', function (): void {
    Log::spy();

    postMeta(['entry' => [['changes' => [['field' => 'phone_number_quality_update', 'value' => ['display_phone_number' => '528186875767', 'event' => 'FLAGGED', 'current_limit' => 'TIER_1K']]]]]]);

    Log::shouldHaveReceived('warning')->with('whatsapp.quality_changed', ['event' => 'FLAGGED', 'current_limit' => 'TIER_1K'])->once();
});
