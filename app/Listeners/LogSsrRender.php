<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Log;

/**
 * One line for every answer Node gives to a render (the requests and latency of the SSR service). A failed connection
 * has no answer, so it leaves no line here: LogSsrRenderFailure reports it. Only the status and the time: never the page.
 */
final class LogSsrRender
{
    public function handle(ResponseReceived $event): void
    {
        if ($event->request->url() !== rtrim(config()->string('inertia.ssr.url'), '/').'/render') {
            return;
        }

        $seconds = $event->response->transferStats?->getTransferTime();

        Log::info('ssr.rendered', [
            'status' => $event->response->status(),
            'duration_ms' => $seconds === null ? null : round($seconds * 1000, 1),
        ]);
    }
}
