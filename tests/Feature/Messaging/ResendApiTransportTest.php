<?php

declare(strict_types=1);

use App\Modules\Messaging\Listeners\StampMailDelivery;
use App\Modules\Messaging\Support\ResendApiTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

mutates(ResendApiTransport::class);

beforeEach(function (): void {
    config(['services.resend.key' => 're_test_key', 'mail.default' => 'resend', 'mail.from.address' => 'notificaciones@example.com', 'mail.from.name' => 'Humetria']);
});

function sendProbe(?string $deliveryId): void
{
    Mail::mailer('resend')->html('<p>Su enlace</p>', function (Illuminate\Mail\Message $message) use ($deliveryId): void {
        $message->to('ana@example.com')->subject('Hola');
        $deliveryId === null || $message->getSymfonyMessage()->getHeaders()->addTextHeader(StampMailDelivery::HEADER, $deliveryId);
    });
}

it('sends by the API of Resend with the key, and the delivery id as a tag instead of a header', function (): void {
    Http::fake(['api.resend.com/*' => Http::response(['id' => 'abc'])]);

    sendProbe('0b6f6b0e-5d7e-4f3c-9b0e-2a1c3d4e5f60');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.resend.com/emails'
        && $request->hasHeader('Authorization', 'Bearer re_test_key')
        && $request['to'] === ['ana@example.com']
        && $request['subject'] === 'Hola'
        && $request['html'] === '<p>Su enlace</p>'
        && str_contains((string) $request['from'], 'notificaciones@example.com')
        && $request['tags'] === [['name' => 'delivery_id', 'value' => '0b6f6b0e-5d7e-4f3c-9b0e-2a1c3d4e5f60']]
        && ! array_key_exists(StampMailDelivery::HEADER, $request['headers'] ?? []));
});

it('sends no tags when the mail is not followed', function (): void {
    Http::fake(['api.resend.com/*' => Http::response(['id' => 'abc'])]);

    sendProbe(null);

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('tags', $request->data()));
});

it('fails loudly when Resend refuses the mail, so the job retries', function (): void {
    Http::fake(['api.resend.com/*' => Http::response(['message' => 'nope'], 422)]);

    expect(fn () => sendProbe(null))->toThrow(TransportException::class);
});
