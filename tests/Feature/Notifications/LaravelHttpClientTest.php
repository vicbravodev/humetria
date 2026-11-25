<?php

declare(strict_types=1);

use App\Modules\Notifications\Exceptions\PushNetworkFailed;
use App\Modules\Notifications\Support\LaravelHttpClient;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;

mutates(LaravelHttpClient::class);

/*
 * The push library speaks PSR-18; the app speaks the HTTP client of Laravel. The adapter is what lets `Http::fake()` and
 * `Http::preventStrayRequests()` cover the push, so no test reaches a real push service.
 */
it('is a PSR-18 client', function (): void {
    expect(new LaravelHttpClient)->toBeInstanceOf(ClientInterface::class);
});

it('passes the method, the address, the headers and the body as they are', function (): void {
    Http::fake(['*' => Http::response('', 201)]);

    (new LaravelHttpClient)->sendRequest(new PsrRequest('POST', 'https://fcm.googleapis.com/fcm/send/abc?x=1', ['TTL' => '3600', 'Urgency' => 'normal', 'Authorization' => 'vapid t=a, k=b', 'Content-Type' => 'application/octet-stream'], 'body'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://fcm.googleapis.com/fcm/send/abc?x=1'
        && $request->header('TTL') === ['3600'] && $request->header('Urgency') === ['normal'] && $request->header('Authorization') === ['vapid t=a, k=b']
        && $request->body() === 'body');
});

it('answers with the status, the headers and the body of the push service', function (): void {
    Http::fake(['*' => Http::response('slow down', 429, ['Retry-After' => '120'])]);

    $response = (new LaravelHttpClient)->sendRequest(new PsrRequest('POST', 'https://fcm.googleapis.com/x'));

    expect($response->getStatusCode())->toBe(429)->and($response->getHeaderLine('Retry-After'))->toBe('120')->and((string) $response->getBody())->toBe('slow down');
});

it('does not follow a redirect: the service that answers 301 is answered as it is', function (): void {
    Http::fake(['*' => Http::response('', 301, ['Location' => 'https://evil.example/'])]);

    $response = (new LaravelHttpClient)->sendRequest(new PsrRequest('POST', 'https://fcm.googleapis.com/x'));

    expect($response->getStatusCode())->toBe(301);
    Http::assertSentCount(1);
});

it('turns a failed connection into the exception the library catches, with its request', function (): void {
    Http::fake(fn () => throw new ConnectionException('down'));
    $request = new PsrRequest('POST', 'https://fcm.googleapis.com/x');

    try {
        (new LaravelHttpClient)->sendRequest($request);
        $thrown = null;
    } catch (NetworkExceptionInterface $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(PushNetworkFailed::class)->and($thrown?->getRequest())->toBe($request);
});

it('does not reach the network for a request nobody faked', function (): void {
    expect(fn () => (new LaravelHttpClient)->sendRequest(new PsrRequest('POST', 'https://fcm.googleapis.com/x')))->toThrow(RuntimeException::class, 'Attempted request');
});
