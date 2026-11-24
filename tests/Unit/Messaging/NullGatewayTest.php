<?php

declare(strict_types=1);

use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Providers\MessagingServiceProvider;
use App\Modules\Messaging\Support\NullWhatsAppGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

mutates(NullWhatsAppGateway::class, MessagingServiceProvider::class);

function sampleMessage(): OutboundMessage
{
    return new OutboundMessage(
        to: '+528112345678',
        template: 'survey_invite_v1',
        language: 'es_MX',
        bodyParameters: ['Acme', '12 de mayo'],
        buttonSuffix: 'a-token',
        callbackData: 'delivery-id',
    );
}

it('resolves the null gateway while WHATSAPP_ENABLED is not set', function (): void {
    expect(config('whatsapp.enabled'))->toBeFalse()
        ->and(app(WhatsAppGateway::class))->toBeInstanceOf(NullWhatsAppGateway::class);
});

it('makes no request and answers disabled', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    $outcome = app(WhatsAppGateway::class)->send(sampleMessage());

    expect($outcome->cause)->toBe(FailureCause::Disabled)
        ->and($outcome->isAccepted())->toBeFalse();
    Http::assertNothingSent();
});

it('logs that it skipped the send, without the phone or the link', function (): void {
    Log::spy();

    app(WhatsAppGateway::class)->send(sampleMessage());

    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => $event === 'whatsapp.send_skipped'
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '8112345678')
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'a-token'))->once();
});

it('refuses to boot with the function on and no recipient keys', function (): void {
    config(['whatsapp.enabled' => true, 'messaging.recipient_keys' => []]);

    (new MessagingServiceProvider(app()))->boot();
})->throws(LogicException::class);

it('boots with the function on and a recipient key', function (): void {
    config(['whatsapp.enabled' => true, 'messaging.recipient_keys' => [1 => 'a-key']]);

    (new MessagingServiceProvider(app()))->boot();

    expect(config('whatsapp.enabled'))->toBeTrue();
});

it('sends no service text either, and logs neither the phone nor the text', function (): void {
    Http::preventStrayRequests();
    Http::fake();
    Log::spy();

    $outcome = app(WhatsAppGateway::class)->sendText('+528112345678', 'Un texto');

    expect($outcome->cause)->toBe(FailureCause::Disabled);
    Http::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => $event === 'whatsapp.send_skipped'
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '8112345678')
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'Un texto'))->once();
});
