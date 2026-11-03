<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Enums\ReportStatus;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Takes the report of a damaged seat from a person's portal. It is tied to their center and their current position and to
 * nobody else: who reports is not recorded, so nobody holds back from reporting.
 */
final readonly class ReportSeat
{
    public function __construct(private CurrentTenant $tenant, private Roster $roster) {}

    public function handle(PortalIdentity $person, string $description): SeatReport
    {
        // The identity was opened a moment ago, so the person is in the roster.
        $workCenterId = ($this->roster->find($person->employeeId) ?? throw new InvalidArgumentException('The person is not in the roster.'))->workCenterId;

        $report = SeatReport::query()->create([
            'tenant_id' => $this->tenant->id(),
            'work_center_id' => $workCenterId,
            'position_id' => PositionAssignment::query()->where('employee_id', $person->employeeId)->whereNull('ends_on')->first()?->position_id,
            'description' => $description,
            'status' => ReportStatus::Open,
            'reported_via_portal' => true,
        ]);

        Log::info('seating.seat_reported', ['report_id' => $report->id, 'work_center_id' => $workCenterId]);

        return $report;
    }
}
