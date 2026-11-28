<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Actions\SendPush;
use App\Modules\Notifications\Enums\PushKind;
use App\Modules\Notifications\Exceptions\PushRateLimited;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The notice to one phone, on the `default` queue and after the commit of whatever said it (if that is undone, nothing is sent). One
 * job per phone, so a 429 for one does not repeat the others. Only ids and the kind travel in the queue.
 */
final class DeliverPush implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    /** @var list<int> */
    public array $backoff = [60, 300]; // @pest-mutate-ignore: IncrementInteger,DecrementInteger,RemoveArrayItem

    public function __construct(public readonly string $tenantId, public readonly string $subscriptionId, public readonly PushKind $kind) {}

    public function handle(Companies $companies, SendPush $send): void
    {
        if ($companies->enter($this->tenantId)->blocked) {
            return;
        }

        try {
            $send->handle($this->subscriptionId, $this->kind);
        } catch (PushRateLimited $limited) {
            // Waits what the service asked; a failure (PushUnavailable) goes to the queue's backoff.
            $this->release($limited->retryAfter);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('push.failed', ['subscription_id' => $this->subscriptionId, 'reason' => 'exhausted']);
    }
}
