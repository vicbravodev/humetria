<?php

declare(strict_types=1);

namespace App\Modules\Demos\Jobs;

use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Demos\Models\DemoRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Resets the demo company for the console: wipes it, live visitors' traces included, and tells its story again. Only
 * its run travels in the job, and the run shows each step. Once only: a half-told story is not safe to tell again on top
 * (the next run starts by wiping it). A failure stays on the run with its class and step, never its message, and the job
 * ends without rethrowing it, so the message never reaches failed_jobs either.
 */
final class ResetDemo implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Seconds: a preparation takes about 25 s on an idle server and a few minutes on a busy one. */
    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $runId)
    {
        // Its own connection and queue: redis' retry_after (90 s) would hand a long preparation to a second worker.
        $this->onConnection('redis-long')->onQueue('demo');
    }

    public function handle(DemoWorkspace $workspace): void
    {
        $run = DemoRun::query()->findOrFail($this->runId);

        // Taken over while this job waited in the queue: the run that took over may be telling the story now.
        if ($run->status !== 'running') {
            return;
        }

        try {
            $run->reach('Borrando la demo');
            $workspace->reset();
            $workspace->prepare($run->reach(...));
        } catch (Throwable $error) {
            $run->fail($error);

            return;
        }

        $run->finish();
    }

    /** The worker gave up on it (its time ran out): the run must not stay running. */
    public function failed(Throwable $error): void
    {
        DemoRun::query()->find($this->runId)?->fail($error);
    }
}
