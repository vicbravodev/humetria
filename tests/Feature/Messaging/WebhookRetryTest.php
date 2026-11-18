<?php

declare(strict_types=1);

use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Models\WebhookEvent;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Messaging\DeliveryFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
 * A webhook that fails halfway answers 500 and the provider sends it again. The id of the event is remembered in the same
 * transaction that applies it, so the retry is processed instead of being taken for a repeat: a STOP lost this way would mean
 * writing to someone who asked us to stop.
 */

beforeEach(function (): void {
    config(['whatsapp.app_secrets' => ['retry-secret'], 'messaging.recipient_keys' => [1 => base64_encode('a-key')],
        'services.resend.webhook_secrets' => ['whsec_'.base64_encode('the-secret')], 'services.resend.webhook_tolerance_seconds' => 300]);
});

/** Makes the next creation of a model fail once, as a lock timeout or a deadlock would. */
function failNextCreate(string $model): void
{
    $armed = true;
    Event::listen("eloquent.creating: {$model}", function () use (&$armed): void {
        if ($armed) {
            $armed = false;

            throw new RuntimeException('lock timeout');
        }
    });
}

function retryMeta(array $payload): TestResponse
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    return test()->call('POST', route('messaging.webhooks.meta.receive'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'retry-secret')], $raw);
}

function retryResend(array $payload, string $id): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = CarbonImmutable::now()->getTimestamp();

    return test()->call('POST', route('messaging.webhooks.resend.receive'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_SVIX_ID' => $id,
        'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
        'HTTP_SVIX_SIGNATURE' => 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", 'the-secret', true)),
    ], $body);
}

it('processes the retry of a Meta status that failed while it was applied', function (): void {
    $delivery = DeliveryFactory::new()->create(['tenant_id' => Tenant::factory()->create()->id, 'provider_message_id' => 'wamid.A', 'status' => DeliveryStatus::Sent]);
    $payload = ['entry' => [['changes' => [['field' => 'messages', 'value' => ['statuses' => [['id' => 'wamid.A', 'status' => 'delivered', 'timestamp' => '1795000000']]]]]]]];
    failNextCreate(DeliveryEvent::class);

    expect(retryMeta($payload)->getStatusCode())->toBe(500)
        ->and(WebhookEvent::query()->count())->toBe(0)
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::Sent);

    expect(retryMeta($payload)->getStatusCode())->toBe(200)
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered)
        ->and(WebhookEvent::query()->count())->toBe(1);

    // And a third delivery of the same event is the repeat it always was.
    retryMeta($payload);
    expect(DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'delivered')->count())->toBe(1);
});

it('processes the retry of a STOP that failed while the opt-out was written', function (): void {
    $payload = ['entry' => [['changes' => [['field' => 'messages', 'value' => ['messages' => [['from' => '5218186875767', 'id' => 'wamid.STOP1', 'type' => 'text', 'text' => ['body' => 'STOP']]]]]]]]];
    failNextCreate(OptOut::class);

    expect(retryMeta($payload)->getStatusCode())->toBe(500)
        ->and(OptOut::query()->count())->toBe(0)
        ->and(WebhookEvent::query()->count())->toBe(0);

    expect(retryMeta($payload)->getStatusCode())->toBe(200)
        ->and(OptOut::query()->sole()->recipient_hash)->toBe((new RecipientHash)->current('+528186875767')['hash']);
});

it('processes the retry of a Resend event that failed while it was applied', function (): void {
    $delivery = Delivery::factory()->mail()->create(['provider_message_id' => null]);
    $payload = ['type' => 'email.bounced', 'created_at' => '2026-11-18T16:00:00.000Z', 'data' => ['email_id' => 'r1', 'tags' => ['delivery_id' => $delivery->id], 'bounce' => ['type' => 'Permanent']]];
    $id = 'msg_'.Str::random(8);
    failNextCreate(DeliveryEvent::class);

    expect(retryResend($payload, $id)->getStatusCode())->toBe(500)
        ->and(WebhookEvent::query()->count())->toBe(0);

    expect(retryResend($payload, $id)->getStatusCode())->toBe(200)
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::Bounced)
        ->and(WebhookEvent::query()->count())->toBe(1);
});

it('processes the retry of a tap on «Dejar de recibir» that failed while the opt-out was written', function (): void {
    $label = App\Modules\Messaging\Support\TemplateButton::OPT_OUT_LABEL;
    $payload = ['entry' => [['changes' => [['field' => 'messages', 'value' => ['messages' => [['from' => '5218186875767', 'id' => 'wamid.BTN1', 'timestamp' => '1795000000', 'type' => 'button', 'button' => ['payload' => $label, 'text' => $label]]]]]]]]];
    failNextCreate(OptOut::class);

    expect(retryMeta($payload)->getStatusCode())->toBe(500)
        ->and(OptOut::query()->count())->toBe(0)
        ->and(WebhookEvent::query()->count())->toBe(0);

    expect(retryMeta($payload)->getStatusCode())->toBe(200)
        ->and(OptOut::query()->count())->toBe(1);
});
