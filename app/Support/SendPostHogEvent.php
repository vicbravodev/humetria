<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Analytics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One event on its way to PostHog, off the request that caused it: a PostHog that is slow or down never slows or breaks
 * a page. Best effort on purpose (no retries, and a failure is only logged): losing an event is cheaper than an error
 * about the error reporter. The token is read when it runs, so it never sits in the queue.
 */
final class SendPostHogEvent implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /** @param  array<string, mixed>  $properties */
    public function __construct(
        private readonly string $event,
        private readonly string $distinctId,
        private readonly array $properties,
    ) {}

    public function handle(): void
    {
        $token = Analytics::serverToken();

        if ($token === null) {
            return;
        }

        try {
            Http::timeout(5)->asJson()->post(Analytics::HOST.'/i/v0/e/', [
                'api_key' => $token,
                'event' => $this->event,
                'distinct_id' => $this->distinctId,
                'properties' => $this->properties,
            ])->throw();
        } catch (ConnectionException|RequestException $exception) {
            // The class only: a transport message can quote what was being sent.
            Log::warning('posthog.send_failed', ['exception' => class_basename($exception)]);
        }
    }
}
