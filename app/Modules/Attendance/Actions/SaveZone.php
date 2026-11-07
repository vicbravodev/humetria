<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates a zone in a work center in use, or updates one of the company's. The log carries ids only, never coordinates.
 */
final readonly class SaveZone
{
    public function __construct(private CurrentTenant $tenant, private WorkCenters $workCenters) {}

    /** @throws WorkCenterNotAvailable when the work center is archived or foreign, or the zone is not the company's */
    public function handle(string $workCenterId, string $name, float $lat, float $lng, int $radius, ?string $zoneId = null): AttendanceZone
    {
        $zone = DB::transaction(function () use ($workCenterId, $name, $lat, $lng, $radius, $zoneId): AttendanceZone {
            if ($this->workCenters->lockActive($workCenterId) === null) {
                throw new WorkCenterNotAvailable;
            }

            $zone = $zoneId === null
                ? new AttendanceZone(['tenant_id' => $this->tenant->id()])
                : AttendanceZone::query()->find($zoneId) ?? throw new WorkCenterNotAvailable;
            $zone->fill(['work_center_id' => $workCenterId, 'name' => $name, 'latitude' => $lat, 'longitude' => $lng, 'radius_m' => $radius])->save();

            return $zone;
        });

        Log::info('attendance.zone_saved', ['zone_id' => $zone->id, 'work_center_id' => $zone->work_center_id]);

        return $zone;
    }
}
