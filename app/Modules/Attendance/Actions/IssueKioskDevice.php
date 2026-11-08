<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A new QR screen for a work center in use. Returns its token, the only time it exists in clear: only its hash is kept. To
 * the audit trail and the log with ids only.
 */
final readonly class IssueKioskDevice
{
    public function __construct(private CurrentTenant $tenant, private WorkCenters $workCenters, private RecordAudit $audit) {}

    /** @throws WorkCenterNotAvailable when the work center is archived or not the company's */
    public function handle(string $workCenterId): string
    {
        $token = Str::random(48);

        $kiosk = DB::transaction(function () use ($workCenterId, $token): KioskDevice {
            if ($this->workCenters->lockActive($workCenterId) === null) {
                throw new WorkCenterNotAvailable;
            }

            $kiosk = KioskDevice::query()->create([
                'tenant_id' => $this->tenant->id(),
                'work_center_id' => $workCenterId,
                'token_hash' => KioskDevice::hashToken($token),
            ]);
            $this->audit->handle('attendance.kiosk_issued', $kiosk, ['work_center_id' => $workCenterId]);

            return $kiosk;
        });

        Log::info('attendance.kiosk_issued', ['kiosk_id' => $kiosk->id, 'work_center_id' => $workCenterId]);

        return $token;
    }
}
