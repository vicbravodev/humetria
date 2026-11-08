<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Models\KioskDevice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stops a QR screen of the company from showing codes, to the audit trail and the log with ids only. Revoking it again
 * changes nothing: it keeps when it was first.
 */
final readonly class RevokeKioskDevice
{
    public function __construct(private RecordAudit $audit) {}

    /** @throws ModelNotFoundException when the screen is not the company's */
    public function handle(string $kioskId): void
    {
        $kiosk = KioskDevice::query()->findOrFail($kioskId);

        if ($kiosk->revoked_at !== null) {
            return;
        }

        DB::transaction(function () use ($kiosk): void {
            $kiosk->update(['revoked_at' => now()]);
            $this->audit->handle('attendance.kiosk_revoked', $kiosk, ['work_center_id' => $kiosk->work_center_id]);
        });
        Log::info('attendance.kiosk_revoked', ['kiosk_id' => $kiosk->id]);
    }
}
