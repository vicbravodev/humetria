<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Where a person without a session was going when they were sent to enter: a page of theirs (`/me/{slug}/...`) of the same
 * company, on this site, and nothing else (no other site, company or the panel, so the entrance is never an open redirect).
 * It is kept in the session apart from the panel's, checked again when it is used, and used once.
 */
final readonly class WorkerDestination
{
    private const string KEY = 'worker_destination';

    /** Keeps the page a visitor asked for; a form sent without a session is not a destination. */
    public function remember(Request $request, string $slug): void
    {
        if ($request->isMethod('GET') && self::allowed($request->fullUrl(), $slug)) {
            session()->put(self::KEY, $request->fullUrl());
        }
    }

    /** Where to take the person who just entered: the page they were going to, or their start. Forgotten either way. */
    public function pull(string $slug): string
    {
        $kept = session()->pull(self::KEY);

        return is_string($kept) && self::allowed($kept, $slug) ? $kept : route('worker.home', $slug);
    }

    /** A page under the person's start of that company, on this site, whose path names only plain segments. */
    private static function allowed(string $url, string $slug): bool
    {
        $prefix = url("/me/{$slug}").'/';

        return Str::startsWith($url, $prefix) && preg_match('#^[A-Za-z0-9_/-]+(\?.*)?$#', Str::after($url, $prefix)) === 1;
    }
}
