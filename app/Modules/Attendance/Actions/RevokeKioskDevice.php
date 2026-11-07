<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Models\KioskDevice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * Stops a QR screen of the company from showing codes. Revoking it again changes nothing: it keeps when it was first.
 */
final readonly class RevokeKioskDevice
{
    /** @throws ModelNotFoundException when the screen is not the company's */
    public function handle(string $kioskId): void
    {
        $kiosk = KioskDevice::query()->findOrFail($kioskId);

        if ($kiosk->revoked_at !== null) {
            return;
        }

        $kiosk->update(['revoked_at' => now()]);
        Log::info('attendance.kiosk_revoked', ['kiosk_id' => $kiosk->id]);
    }
}
