<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Exceptions\PushNetworkFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The push library speaks PSR-18; the app's HTTP goes through the client of Laravel, so `Http::fake()` and `Http::preventStrayRequests()`
 * cover the push. A redirect is not followed: the push service answers or it does not.
 */
final readonly class LaravelHttpClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return Http::withHeaders($request->getHeaders())
                ->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type') ?: 'application/octet-stream')
                ->withoutRedirecting()
                ->timeout(10)
                ->send($request->getMethod(), (string) $request->getUri())
                ->toPsrResponse();
        } catch (ConnectionException) {
            throw new PushNetworkFailed($request);
        }
    }
}
