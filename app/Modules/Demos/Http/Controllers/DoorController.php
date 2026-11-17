<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Modules\Demos\Actions\AdmitVisitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The door of the demo (the QR of a live demonstration): lets each visitor in as their own demo worker. The
 * cookie that remembers which one is encrypted and authenticated by the app (EncryptCookies): a forged one reads as none.
 */
final readonly class DoorController
{
    public const string COOKIE = 'demo_door';

    public function __invoke(Request $request, string $code, AdmitVisitor $admit): RedirectResponse|Response
    {
        $cookie = $request->cookie(self::COOKIE);
        $result = $admit->handle($code, is_string($cookie) ? $cookie : null);

        if ($result->state === 'admitted' && $result->entryUrl !== null && $result->assignmentId !== null && $result->until !== null) {
            // The cookie lasts no longer than the session that let the visitor in.
            $minutes = max(1, intdiv(max(0, $result->until->getTimestamp() - now()->getTimestamp()) + 59, 60));

            // The address is a one-time link that opens the person's session (WorkerAccess): no cache keeps it.
            return redirect()->away($result->entryUrl, 302, ['Cache-Control' => 'no-store, private'])
                ->withCookie(Cookie::make(self::COOKIE, $result->assignmentId, $minutes, '/demo', null, null, true, false, 'lax'));
        }

        // Closed, expired, unknown or of another company: the same page, so nobody can tell them apart.
        return Inertia::render('demos/door', ['state' => $result->state === 'full' ? 'full' : 'closed']);
    }
}
