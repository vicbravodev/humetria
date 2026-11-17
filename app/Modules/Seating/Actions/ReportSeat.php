<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Enums\ReportStatus;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Takes the report of a damaged seat from a person's portal. It is tied to their center and to nothing else: neither who
 * reports nor their position is recorded (in a position of two or three people the position would point at them), so
 * nobody holds back from reporting. Fixing the seat needs only the center and what the person wrote.
 */
final readonly class ReportSeat
{
    public function __construct(private CurrentTenant $tenant, private Roster $roster) {}

    public function handle(WorkerIdentity $person, string $description): SeatReport
    {
        // The session holds only for an active person, so the person is in the roster.
        $workCenterId = ($this->roster->find($person->employeeId) ?? throw new InvalidArgumentException('The person is not in the roster.'))->workCenterId;

        $report = SeatReport::query()->create([
            'tenant_id' => $this->tenant->id(),
            'work_center_id' => $workCenterId,
            'description' => $description,
            'status' => ReportStatus::Open,
            'reported_via_portal' => true,
        ]);

        Log::info('seating.seat_reported', ['report_id' => $report->id, 'work_center_id' => $workCenterId]);

        return $report;
    }
}
