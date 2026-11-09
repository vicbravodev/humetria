<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Models\AttendanceDevice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stops a person's linked device of the company from marking, at once: its key no longer verifies. The revocation goes to
 * the audit trail. Revoking it again changes nothing: it keeps when it was first.
 */
final readonly class RevokeDevice
{
    public function __construct(private RecordAudit $audit) {}

    /** @throws ModelNotFoundException when the device is not the company's */
    public function handle(string $deviceId): void
    {
        $device = AttendanceDevice::query()->findOrFail($deviceId);

        // One conditional UPDATE: of two revocations at once, only one writes, so the audit trail has it once.
        $revoked = DB::transaction(function () use ($device): bool {
            if (AttendanceDevice::query()->whereKey($device->id)->whereNull('revoked_at')->update(['revoked_at' => CarbonImmutable::now()]) !== 1) {
                return false;
            }

            $this->audit->handle('attendance.device_revoked', $device, ['employee_id' => $device->employee_id]);

            return true;
        });

        if ($revoked) {
            Log::info('attendance.device_revoked', ['device_id' => $device->id, 'employee_id' => $device->employee_id]);
        }
    }
}
