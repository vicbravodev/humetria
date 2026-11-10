<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\ActivateKioskDevice;
use App\Modules\Attendance\Actions\IssueKioskDevice;
use App\Modules\Attendance\Actions\ReissueKioskDevice;
use App\Modules\Attendance\Actions\RevokeKioskDevice;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Http\Requests\KioskRequest;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Support\QrImage;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The QR screen of a work center: RH issues and revokes it in the panel; the screen (a fixed tablet, no account) shows the
 * center's rotating code. Its link opens once: the cookie (httpOnly) gets a new secret and the link dies; the secret is never in
 * the page or in an address.
 * A screen without a valid token, revoked, of another company or of an archived center gets the same 404.
 */
final class KioskController
{
    public const string COOKIE = 'attendance_kiosk';

    /** A screen stays on for years; RH ends it by revoking it, not by letting the cookie lapse. */
    private const int COOKIE_MINUTES = 60 * 24 * 365 * 5;

    public function __construct(private readonly Companies $companies, private readonly WorkCenters $workCenters) {}

    public function store(KioskRequest $request, mixed $tenant, IssueKioskDevice $issue): RedirectResponse
    {
        try {
            $token = $issue->handle($request->string('work_center_id')->toString());
        } catch (WorkCenterNotAvailable) {
            abort(404);
        }

        Inertia::flash('kioskLink', route('attendance.device.kiosk.activate', [$tenant, $token]));

        return back();
    }

    /** A new link for a screen never seen, shown once: the one before dies (a link preview may have opened it). */
    public function reissue(mixed $tenant, string $kiosk, ReissueKioskDevice $reissue): RedirectResponse
    {
        Gate::authorize('manage-attendance');
        $token = $reissue->handle($kiosk);

        if ($token === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Esa pantalla ya muestra el código o está revocada. Para cambiarla, revócala y emite otra.']);
        } else {
            Inertia::flash('kioskLink', route('attendance.device.kiosk.activate', [$tenant, $token]));
        }

        return back();
    }

    public function destroy(mixed $tenant, string $kiosk, RevokeKioskDevice $revoke): RedirectResponse
    {
        Gate::authorize('manage-attendance');
        $revoke->handle($kiosk);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pantalla revocada. Ya no muestra el código.']);

        return back();
    }

    public function activate(string $tenant, string $token, ActivateKioskDevice $activate): RedirectResponse
    {
        $secret = $activate->handle($token);
        abort_if($secret === null, 404);

        // Lax, not Strict: the link is tapped in an email or a chat, a cross-site navigation whose redirect would go without a
        // Strict cookie. The cookie only lets the screen read its code.
        return redirect()->route('attendance.device.kiosk.show', $tenant)
            ->withCookie(cookie(self::COOKIE, $secret, self::COOKIE_MINUTES, "/a/{$tenant}", httpOnly: true, sameSite: 'lax'));
    }

    public function show(Request $request, string $tenant): Response
    {
        [, $center] = $this->screen($request->cookie(self::COOKIE));

        return Inertia::render('attendance/kiosk', [
            'company' => $this->companies->current()->tradeName,
            'center' => $center->name,
            'qrUrl' => route('attendance.device.kiosk.qr', $tenant),
            'qrSeconds' => config()->integer('attendance.qr.window_seconds'),
            // The clock on the screen is the company's, whatever the tablet's zone.
            'timezone' => config()->string('app.display_timezone'),
        ]);
    }

    public function qr(Request $request, string $tenant): JsonResponse
    {
        [$kiosk] = $this->screen($request->cookie(self::COOKIE));
        $now = CarbonImmutable::now();

        if ($kiosk->last_seen_at === null || $kiosk->last_seen_at->addMinute()->lessThanOrEqualTo($now)) {
            $kiosk->update(['last_seen_at' => $now]);
        }

        $token = QrToken::issue($kiosk->tenant_id, $kiosk->work_center_id, $now);
        // The page of Marcar (a GET) reads the code from the address: a phone camera opens it.
        $url = url("a/{$tenant}/punch").'?'.http_build_query(['q' => $token]);
        $expiresAt = QrToken::expiresAt($now);

        return response()->json([
            'token' => $token,
            'url' => $url,
            'qr' => QrImage::dataUri($url),
            'expiresAt' => $expiresAt->toIso8601String(),
            // Seconds, so the screen's own clock does not matter.
            'expiresIn' => $expiresAt->getTimestamp() - $now->getTimestamp(),
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * The screen of a token and its center in use; 404, the same for every cause, otherwise.
     *
     * @return array{KioskDevice, WorkCenterSummary}
     */
    private function screen(mixed $token): array
    {
        $kiosk = is_string($token) ? KioskDevice::findActive($token) : null;
        $center = $kiosk === null ? null : $this->workCenters->findActive($kiosk->work_center_id);

        abort_if($kiosk === null || $center === null, 404);

        return [$kiosk, $center];
    }
}
