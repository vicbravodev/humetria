<?php

declare(strict_types=1);

use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\SendOutcome;
use App\Modules\Messaging\Support\CloudApiWhatsAppGateway;
use App\Modules\Messaging\Support\FakeWhatsAppGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

mutates(CloudApiWhatsAppGateway::class);

function cloudMessage(): OutboundMessage
{
    return new OutboundMessage(
        to: '+528112345678',
        template: 'survey_invite_v1',
        language: 'es_MX',
        bodyParameters: ['Acme', '12 de mayo'],
        buttonSuffix: 'the-link-token',
        callbackData: 'delivery-uuid',
    );
}

function cloudGateway(): CloudApiWhatsAppGateway
{
    config([
        'whatsapp.enabled' => true,
        'whatsapp.base_url' => 'https://graph.example.test',
        'whatsapp.api_version' => 'v99.0',
        'whatsapp.phone_number_id' => '1234567890',
        'whatsapp.access_token' => 'the-access-token',
        'whatsapp.timeout_seconds' => 3,
    ]);

    return app(CloudApiWhatsAppGateway::class);
}

it('sends the template the way the Cloud API expects it', function (): void {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.ABC']]], 200)]);

    $outcome = cloudGateway()->send(cloudMessage());

    expect($outcome->isAccepted())->toBeTrue()->and($outcome->providerMessageId)->toBe('wamid.ABC');
    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://graph.example.test/v99.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer the-access-token')
            && $body['messaging_product'] === 'whatsapp'
            && $body['to'] === '528112345678'
            && $body['type'] === 'template'
            && $body['biz_opaque_callback_data'] === 'delivery-uuid'
            && $body['template'] === [
                'name' => 'survey_invite_v1',
                'language' => ['code' => 'es_MX'],
                'components' => [
                    ['type' => 'body', 'parameters' => [
                        ['type' => 'text', 'parameter_name' => 'empresa', 'text' => 'Acme'],
                        ['type' => 'text', 'parameter_name' => 'fecha_limite', 'text' => '12 de mayo'],
                    ]],
                    ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => 'the-link-token']]],
                ],
            ];
    });
});

it('sends the header variable by name and the button at its own index', function (): void {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.ABC']]], 200)]);

    cloudGateway()->send(new OutboundMessage('+528112345678', 'engagement_invite_v1', 'es_MX', ['Acme', '12 de mayo'], 'the-link-token', 'delivery-uuid'));

    Http::assertSent(fn (Request $request): bool => $request->data()['template']['components'] === [
        ['type' => 'header', 'parameters' => [['type' => 'text', 'parameter_name' => 'empresa', 'text' => 'Acme']]],
        ['type' => 'body', 'parameters' => [
            ['type' => 'text', 'parameter_name' => 'empresa', 'text' => 'Acme'],
            ['type' => 'text', 'parameter_name' => 'fecha_limite', 'text' => '12 de mayo'],
        ]],
        ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => 'the-link-token']]],
    ]);
});

it('refuses a message whose parameters do not match its template, and sends nothing', function (): void {
    Http::fake();

    expect(fn () => cloudGateway()->send(new OutboundMessage('+528112345678', 'survey_invite_v1', 'es_MX', ['Acme'], 'the-link-token', 'delivery-uuid')))
        ->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

it('does not put the token anywhere but the button', function (): void {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.ABC']]], 200)]);

    cloudGateway()->send(cloudMessage());

    Http::assertSent(function (Request $request): bool {
        $withoutButton = $request->data();
        unset($withoutButton['template']['components'][1]);

        return substr_count((string) json_encode($request->data()), 'the-link-token') === 1
            && ! str_contains((string) json_encode($withoutButton), 'the-link-token');
    });
});

it('answers each Meta error with its cause', function (int $status, int $code, FailureCause $cause): void {
    Http::fake(['*' => Http::response(['error' => ['code' => $code, 'message' => 'x']], $status)]);

    expect(cloudGateway()->send(cloudMessage())->cause)->toBe($cause);
})->with([
    [401, 190, FailureCause::InvalidToken],
    [400, 132001, FailureCause::TemplateNotApproved],
    [400, 131026, FailureCause::NoWhatsappAccount],
    [400, 131047, FailureCause::OutsideWindow],
    [429, 130429, FailureCause::RateLimited],
    [400, 131048, FailureCause::SpamLimited],
    [400, 131049, FailureCause::EcosystemBlocked],
    [500, 131000, FailureCause::ServerError],
    [400, 424242, FailureCause::UnknownError],
]);

it('answers by HTTP status when the body has no error code', function (): void {
    Http::fake(['*' => Http::response('bad gateway', 502)]);

    expect(cloudGateway()->send(cloudMessage())->cause)->toBe(FailureCause::ServerError);
});

it('treats an accepted response without a message id as a server error', function (): void {
    Http::fake(['*' => Http::response(['messages' => []], 200)]);

    expect(cloudGateway()->send(cloudMessage())->cause)->toBe(FailureCause::ServerError);
});

it('answers timed_out when the connection fails, since it cannot know whether it left', function (): void {
    Http::fake(fn (): never => throw new ConnectionException('timeout'));

    expect(cloudGateway()->send(cloudMessage())->cause)->toBe(FailureCause::TimedOut);
});

it('is the gateway bound when the function is on', function (): void {
    config(['whatsapp.enabled' => true, 'messaging.recipient_keys' => [1 => 'k']]);
    app()->forgetInstance(WhatsAppGateway::class);
    (new App\Modules\Messaging\Providers\MessagingServiceProvider(app()))->register();

    expect(app(WhatsAppGateway::class))->toBeInstanceOf(CloudApiWhatsAppGateway::class);
});

it('rejects a phone that is not a Mexican mobile without calling Meta', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    $message = new OutboundMessage('+14155550123', 'survey_invite_v1', 'es_MX', ['a', 'b'], 't', 'id');

    expect(cloudGateway()->send($message)->cause)->toBe(FailureCause::NoWhatsappAccount);
    Http::assertNothingSent();
});

it('behaves like the fake for the outcomes a caller sees', function (): void {
    $fake = new FakeWhatsAppGateway;
    $fake->script(SendOutcome::failed(FailureCause::NoWhatsappAccount));
    Http::fake(['*' => Http::response(['error' => ['code' => 131026]], 400)]);

    expect(cloudGateway()->send(cloudMessage())->cause)->toBe($fake->send(cloudMessage())->cause);
});
