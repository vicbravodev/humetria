<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Opens the link of a new QR screen, once: the link's token is swapped for a fresh secret that only the screen's cookie
 * keeps, so the link (which may sit in a log, a browser's history or a chat) dies on first use. Null, changing nothing,
 * for a link already opened, revoked, unknown, of another company or of an archived center.
 */
final readonly class ActivateKioskDevice
{
    public function __construct(private WorkCenters $workCenters) {}

    public function handle(string $linkToken): ?string
    {
        $pending = KioskDevice::query()->where('token_hash', KioskDevice::hashToken($linkToken))->whereNull('activated_at')->whereNull('revoked_at');
        $kiosk = (clone $pending)->first();

        if ($kiosk === null || $this->workCenters->findActive($kiosk->work_center_id) === null) {
            return null;
        }

        $secret = Str::random(48);

        // One conditional UPDATE: of two requests opening the same link at once, only one finds it still pending.
        if ($pending->update(['token_hash' => KioskDevice::hashToken($secret), 'activated_at' => CarbonImmutable::now()]) !== 1) {
            return null;
        }

        Log::info('attendance.kiosk_activated', ['kiosk_id' => $kiosk->id]);

        return $secret;
    }
}
