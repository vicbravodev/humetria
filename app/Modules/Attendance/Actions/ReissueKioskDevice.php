<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Models\KioskDevice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A new link for a QR screen that never showed its code: the link before dies, and so does the cookie of whoever opened it
 * (a chat's or a mail's link preview opens a link before the tablet does). Returns the new token, the only time it exists in
 * clear; null, changing nothing, for a screen revoked or already showing the code (revoke it and issue another).
 */
final readonly class ReissueKioskDevice
{
    public function __construct(private RecordAudit $audit) {}

    /** @throws ModelNotFoundException when the screen is not the company's */
    public function handle(string $kioskId): ?string
    {
        $token = Str::random(48);

        $kiosk = DB::transaction(function () use ($kioskId, $token): ?KioskDevice {
            $kiosk = KioskDevice::query()->lockForUpdate()->findOrFail($kioskId);

            if ($kiosk->revoked_at !== null || $kiosk->last_seen_at !== null) {
                return null;
            }

            $kiosk->update(['token_hash' => KioskDevice::hashToken($token), 'activated_at' => null]);
            $this->audit->handle('attendance.kiosk_reissued', $kiosk, ['work_center_id' => $kiosk->work_center_id]);

            return $kiosk;
        });

        if ($kiosk === null) {
            return null;
        }

        Log::info('attendance.kiosk_reissued', ['kiosk_id' => $kiosk->id]);

        return $token;
    }
}
