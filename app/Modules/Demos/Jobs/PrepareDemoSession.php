<?php

declare(strict_types=1);

namespace App\Modules\Demos\Jobs;

use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Demos\Models\DemoSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Prepares the demo for a session: resets it and tells its story again, then opens the session to the audience. Like
 * PrepareDemo, once only, and a failure stays on the run with its class and step; the session fails with it.
 */
final class PrepareDemoSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Seconds: a preparation takes about 25 s on an idle server and a few minutes on a busy one. */
    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $runId, public readonly string $sessionId)
    {
        // Its own connection and queue: redis' retry_after (90 s) would hand a long preparation to a second worker.
        $this->onConnection('redis-long')->onQueue('demo');
    }

    public function handle(DemoWorkspace $workspace): void
    {
        $run = DemoRun::query()->findOrFail($this->runId);
        $session = DemoSession::query()->findOrFail($this->sessionId);

        // Taken over while this job waited in the queue: the run that took over may be telling the story now.
        if ($run->status !== 'running') {
            return;
        }

        try {
            $workspace->prepare($run->reach(...));
        } catch (Throwable $error) {
            $run->fail($error);
            $session->markFailed();

            return;
        }

        $run->finish();
        $session->markReady(null);
    }

    /** The worker gave up on it (its time ran out): neither the run nor the session may stay open. */
    public function failed(Throwable $error): void
    {
        DemoRun::query()->find($this->runId)?->fail($error);
        DemoSession::query()->find($this->sessionId)?->markFailed();
    }
}
