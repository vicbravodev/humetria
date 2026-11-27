<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Demos\Models\DemoSession;
use Illuminate\Support\Facades\Log;

/**
 * The backstop of «Terminar sesión», every hour: a session open for more than EXPIRES_AFTER_HOURS ends (its reset wipes
 * what the audience left: «expired»). A younger one whose run died frees the lock: preparing, it fails, since no audience
 * reached it («failed»); ending, its reset starts again («retried»). Anything else is left alone («none»).
 */
final readonly class EndStaleDemoSession
{
    public const int EXPIRES_AFTER_HOURS = 24;

    public function __construct(private EndDemoSession $end) {}

    /** @return 'expired'|'failed'|'retried'|'none' */
    public function handle(): string
    {
        $session = DemoSession::current();

        if ($session === null) {
            return 'none';
        }

        if ($session->started_at->lt(now()->subHours(self::EXPIRES_AFTER_HOURS))) {
            Log::info('demo.session_expired', ['session_id' => $session->id]);
            $this->end->handle();

            return 'expired';
        }

        $stale = now()->subMinutes(DemoRun::STALE_AFTER_MINUTES);

        if ($session->status === DemoSessionStatus::Preparing) {
            $run = $session->run_id === null ? null : DemoRun::query()->find($session->run_id);

            if (! ($run === null ? $session->started_at->lt($stale) : $this->died($run))) {
                return 'none';
            }

            $session->markFailed();

            return 'failed';
        }

        // The reset is the latest run. A finished one is left alone: the reset may be about to mark the session ended,
        // or a click may be about to start it.
        $run = DemoRun::current();

        if ($session->status !== DemoSessionStatus::Ending || $run === null || ! $this->died($run)) {
            return 'none';
        }

        $this->end->handle();

        return 'retried';
    }

    /** It failed, or a worker died running it: it no longer holds the lock. */
    private function died(DemoRun $run): bool
    {
        return $run->status === 'failed' || ($run->status === 'running' && $run->started_at->lt(now()->subMinutes(DemoRun::STALE_AFTER_MINUTES)));
    }
}
