<?php

declare(strict_types=1);

use App\Modules\Messaging\Actions\HandleResendEvents;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

mutates(HandleResendEvents::class);

beforeEach(function (): void {
    $this->secret = 'whsec_'.base64_encode('the-secret');
    config(['services.resend.webhook_secrets' => [$this->secret], 'services.resend.webhook_tolerance_seconds' => 300]);
    $this->delivery = Delivery::factory()->mail()->create(['provider_message_id' => null]);
});

/**
 * @param  array<string, mixed>  $data
 */
function resendBody(string $type, string $deliveryId, array $data = []): string
{
    return json_encode([
        'type' => $type,
        'created_at' => '2026-11-18T16:00:00.000Z',
        'data' => ['email_id' => 'resend-email-1', 'tags' => ['delivery_id' => $deliveryId], ...$data],
    ], JSON_THROW_ON_ERROR);
}

function postResend(string $body, ?string $id = null, ?string $secret = null, ?int $timestamp = null, ?string $signature = null): Illuminate\Testing\TestResponse
{
    $id ??= 'msg_'.Str::random(10);
    $timestamp ??= CarbonImmutable::now()->getTimestamp();
    $key = (string) base64_decode(substr($secret ?? test()->secret, 6), true);
    $signature ??= 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

    return test()->call('POST', route('messaging.webhooks.resend.receive'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_SVIX_ID' => $id,
        'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
        'HTTP_SVIX_SIGNATURE' => $signature,
    ], $body);
}

it('rejects a request without signature, with a wrong one, or too old, and writes nothing', function (): void {
    $body = resendBody('email.delivered', $this->delivery->id);

    $this->call('POST', route('messaging.webhooks.resend.receive'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertForbidden();
    postResend($body, signature: 'v1,AAAA')->assertForbidden();
    postResend($body, secret: 'whsec_'.base64_encode('another'))->assertForbidden();
    postResend($body, timestamp: CarbonImmutable::now()->getTimestamp() - 301)->assertForbidden();

    expect(DeliveryEvent::query()->count())->toBe(0)
        ->and(WebhookEvent::query()->count())->toBe(0)
        ->and($this->delivery->refresh()->status)->toBe(DeliveryStatus::Created);
});

it('answers 400 to a body that is not JSON, signed and all, and logs a fixed event without the body', function (): void {
    Log::spy();

    postResend('{not json')->assertStatus(400);
    postResend('"a string"')->assertStatus(400);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $event, array $context = []): bool => $event === 'mail.webhook_malformed' && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'json'))->twice();
});

it('maps each Resend event to the status of the message', function (string $type, array $data, DeliveryStatus $status): void {
    postResend(resendBody($type, $this->delivery->id, $data))->assertOk();

    expect($this->delivery->refresh()->status)->toBe($status)
        ->and(DeliveryEvent::query()->where('delivery_id', $this->delivery->id)->latest('created_at')->first()?->status)->toBe($status);
})->with([
    'sent' => ['email.sent', [], DeliveryStatus::Sent],
    'delivered' => ['email.delivered', [], DeliveryStatus::Delivered],
    'delayed' => ['email.delivery_delayed', [], DeliveryStatus::Delayed],
    'complained' => ['email.complained', [], DeliveryStatus::Complained],
    'permanent bounce' => ['email.bounced', ['bounce' => ['type' => 'Permanent', 'subType' => 'General']], DeliveryStatus::Bounced],
    'temporary bounce' => ['email.bounced', ['bounce' => ['type' => 'Temporary', 'subType' => 'MailboxFull']], DeliveryStatus::Delayed],
    'transient bounce' => ['email.bounced', ['bounce' => ['type' => 'Transient']], DeliveryStatus::Delayed],
]);

it('treats a bounce with no type as permanent, the safe reading', function (): void {
    postResend(resendBody('email.bounced', $this->delivery->id))->assertOk();

    expect($this->delivery->refresh()->status)->toBe(DeliveryStatus::Bounced);
});

it('stamps the time of the event, not the time it arrived', function (): void {
    postResend(resendBody('email.delivered', $this->delivery->id))->assertOk();

    expect(DeliveryEvent::query()->where('status', DeliveryStatus::Delivered)->firstOrFail()->occurred_at->toIso8601String())->toBe('2026-11-18T16:00:00+00:00');
});

it('keeps the email id of Resend on the first event and never replaces it', function (): void {
    postResend(resendBody('email.sent', $this->delivery->id))->assertOk();
    postResend(resendBody('email.delivered', $this->delivery->id, ['email_id' => 'resend-email-2']))->assertOk();

    expect($this->delivery->refresh()->provider_message_id)->toBe('resend-email-1');
});

it('processes an event once: the same svix id again changes nothing', function (): void {
    $body = resendBody('email.delivered', $this->delivery->id);

    postResend($body, id: 'msg_same')->assertOk();
    postResend($body, id: 'msg_same')->assertOk();

    expect(DeliveryEvent::query()->where('status', DeliveryStatus::Delivered)->count())->toBe(1);
});

it('ignores, with 200, an event type it does not map, a message it does not know, and a WhatsApp message', function (): void {
    $whatsapp = Delivery::factory()->create();

    postResend(resendBody('email.opened', $this->delivery->id))->assertOk();
    postResend(resendBody('email.delivered', (string) Str::uuid()))->assertOk();
    postResend(resendBody('email.delivered', $whatsapp->id))->assertOk();
    postResend((string) json_encode(['type' => 'email.delivered', 'data' => ['email_id' => 'x']]))->assertOk();
    postResend((string) json_encode(['type' => 'email.delivered', 'data' => ['tags' => ['delivery_id' => 'not-a-uuid']]]))->assertOk();

    expect($this->delivery->refresh()->status)->toBe(DeliveryStatus::Created)
        ->and($whatsapp->refresh()->status)->toBe(DeliveryStatus::Created)
        ->and(DeliveryEvent::query()->where('status', '!=', DeliveryStatus::Created)->count())->toBe(0);
});

it('writes the event in the company of the message, whichever one it is', function (): void {
    $other = Delivery::factory()->mail()->create(['provider_message_id' => null]);

    postResend(resendBody('email.bounced', $other->id, ['email_id' => 'resend-email-other', 'bounce' => ['type' => 'Permanent']]))->assertOk();

    expect(DeliveryEvent::query()->where('status', DeliveryStatus::Bounced)->firstOrFail()->tenant_id)->toBe($other->tenant_id)
        ->and($other->tenant_id)->not->toBe($this->delivery->tenant_id)
        ->and($this->delivery->refresh()->status)->toBe(DeliveryStatus::Created);
});

it('logs neither the payload nor an address', function (): void {
    Log::spy();

    postResend(resendBody('email.bounced', $this->delivery->id, ['to' => ['ana@example.com'], 'bounce' => ['type' => 'Permanent']]))->assertOk();

    Log::shouldNotHaveReceived('debug');
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'ana@example.com'))->atLeast()->once();
});
