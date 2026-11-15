<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Contracts\SeatingRoute;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\CompanySeatingRoute;
use App\Modules\Seating\Support\ComplianceFacts;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

mutates(CompanySeatingRoute::class, ComplianceFacts::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
});

/** A center with a standing position held by one person nobody assessed, and nothing else. */
function standingCenter(object $test): WorkCenter
{
    $center = WorkCenter::factory()->for($test->tenant)->create();
    $where = ['tenant_id' => $test->tenant->id, 'work_center_id' => $center->id];
    SeatingProfile::factory()->create($where);
    $position = StandingPosition::factory()->create($where);
    $person = Employee::factory()->create($where);
    PositionAssignment::factory()->create(['tenant_id' => $test->tenant->id, 'employee_id' => $person->id, 'position_id' => $position->id]);
    CommitteeAssignment::factory()->create($where + ['user_id' => memberOf($test->tenant, TenantRole::Committee)->id]);

    return $center;
}

it('tells each center where it stands in its route, and that it does not apply where nobody works standing', function (): void {
    $plant = standingCenter($this);
    $offices = WorkCenter::factory()->for($this->tenant)->create();
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $offices->id, 'no_standing_positions' => true, 'declared_on' => '2026-10-03']);

    expect(app(SeatingRoute::class)->progress([$plant->id, $offices->id, WorkCenter::factory()->create()->id]))->toEqual([
        // Its position is held and the Comisión sits; it lacks seats, the assessment, the regulation and the diagnosis.
        $plant->id => new RouteProgress(true, [true, false, false, false, false, false], 'pon asientos donde hacen falta'),
        $offices->id => RouteProgress::notApplicable('Nadie trabaja de pie · constancia del 3 oct 2026'),
    ]);
});

it('reads every center in the same number of queries, however many there are', function (int $centers): void {
    $ids = array_map(fn (): string => standingCenter($this)->id, range(1, $centers));
    DB::flushQueryLog();
    DB::enableQueryLog();

    app(SeatingRoute::class)->progress($ids);

    expect(count(DB::getQueryLog()))->toBe(QUERIES_OF_THE_SEATING_ROUTE);
})->with([1, 3]);

// Measured, then fixed so that a query per center breaks the test.
const QUERIES_OF_THE_SEATING_ROUTE = 17;
