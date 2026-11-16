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
 * The door of the demo (the QR of a live demonstration): sends each visitor to the portal of their own demo worker. The
 * cookie that remembers which one is encrypted and authenticated by the app (EncryptCookies): a forged one reads as none.
 */
final readonly class DoorController
{
    public const string COOKIE = 'demo_door';

    public function __invoke(Request $request, string $code, AdmitVisitor $admit): RedirectResponse|Response
    {
        $cookie = $request->cookie(self::COOKIE);
        $result = $admit->handle($code, is_string($cookie) ? $cookie : null);

        if ($result->state === 'admitted' && $result->portalUrl !== null && $result->assignmentId !== null) {
            // The address is the person's portal, a bearer link: no cache keeps it.
            return redirect()->away($result->portalUrl, 302, ['Cache-Control' => 'no-store, private'])
                ->withCookie(Cookie::make(self::COOKIE, $result->assignmentId, 12 * 60, '/demo', null, null, true, false, 'lax'));
        }

        // Closed, expired, unknown or of another company: the same page, so nobody can tell them apart.
        return Inertia::render('demos/door', ['state' => $result->state === 'full' ? 'full' : 'closed']);
    }
}
