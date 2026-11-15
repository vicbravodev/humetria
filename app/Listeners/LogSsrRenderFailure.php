<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Http\SsrPages;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Ssr\SsrErrorType;
use Inertia\Ssr\SsrRenderFailed;

/**
 * The page is served without Node's render when it fails, so the failure must leave a trace: one line per type and
 * minute, never the page (its props), the stack or the error text (they can quote props) or the query string.
 * A Node that cannot be reached also opens SsrPages' circuit breaker, so a hung Node does not cost every visit its timeout.
 */
final class LogSsrRenderFailure
{
    public function handle(SsrRenderFailed $event): void
    {
        if ($event->type === SsrErrorType::Connection) {
            Cache::put(SsrPages::CIRCUIT_KEY, true, SsrPages::CIRCUIT_SECONDS);
        }

        if (! Cache::add("ssr:failed:{$event->type->value}", 1, 60)) {
            Cache::increment('ssr:failed:count');

            return;
        }

        Log::warning('ssr.render_failed', [
            'type' => $event->type->value,
            'component' => $event->component(),
            'path' => Str::before($event->url(), '?'),
            'hint' => $event->hint,
        ]);
    }
}
