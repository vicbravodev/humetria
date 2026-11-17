<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\CenterMode;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Models\AttendanceCenterMode;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sets how the people of a work center in use mark (criterio 4.5): by the company's policy or in the field. It decides the
 * evidence of their marks, so each change goes to the audit trail.
 */
final readonly class SaveCenterMode
{
    public function __construct(private CurrentTenant $tenant, private WorkCenters $workCenters, private RecordAudit $audit) {}

    /** @throws WorkCenterNotAvailable when the work center is archived or of another company */
    public function handle(string $workCenterId, CenterMode $mode): AttendanceCenterMode
    {
        $saved = DB::transaction(function () use ($workCenterId, $mode): AttendanceCenterMode {
            if ($this->workCenters->lockActive($workCenterId) === null) {
                throw new WorkCenterNotAvailable;
            }

            $saved = AttendanceCenterMode::query()->updateOrCreate(
                ['work_center_id' => $workCenterId],
                ['tenant_id' => $this->tenant->id(), 'mode' => $mode],
            );
            $this->audit->handle('attendance.center_mode_saved', $saved, ['work_center_id' => $workCenterId, 'mode' => $mode->value]);

            return $saved;
        });

        Log::info('attendance.center_mode_saved', ['work_center_id' => $workCenterId, 'mode' => $mode->value]);

        return $saved;
    }
}
