<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
 * What every module does when a person leaves commits with the departure, or not at all: so does what it says in the log.
 * A log of something that was rolled back would tell whoever reads it that it happened.
 */
const LEAVING_LOGS = [
    'employee.deactivated', 'leave.request_trimmed', 'leave.entitlements_voided', 'leave.termination_recorded',
    'seating.assignments_ended', 'telework.teleworker_ended', 'worker_access.access_revoked_on_leaving',
];

beforeEach(function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    Context::add('user_id', User::factory()->create()->id);
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2024-01-01']);
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'hired_on' => '2020-03-01']);
    // Something of hers in every module that acts on a departure.
    $fifth = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 5, 'anniversary_on' => '2025-03-01', 'statutory_days' => 20]);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 6, 'anniversary_on' => '2026-03-01', 'statutory_days' => 22]);
    $crossing = LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'approver_employee_id' => null, 'starts_on' => '2026-02-09', 'ends_on' => '2026-02-20', 'working_days' => 10]);
    LeaveEntry::factory()->of($fifth)->taken(10)->create(['leave_request_id' => $crossing->id]);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id])->id, 'starts_on' => '2026-01-10']);
    TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $plant->id, 'started_at' => '2026-01-10 12:00:00']);
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

it('writes the log of what every module did only once the departure has committed', function (): void {
    Log::spy();

    resolve(DeactivateEmployee::class)->handle($this->ana, effectiveOn: CarbonImmutable::parse('2026-02-15'));

    foreach (LEAVING_LOGS as $event) {
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === $event)->once();
    }
});

it('writes none of them when the departure is rolled back', function (): void {
    Log::spy();

    try {
        DB::transaction(function (): void {
            resolve(DeactivateEmployee::class)->handle($this->ana, effectiveOn: CarbonImmutable::parse('2026-02-15'));

            throw new RuntimeException('Rolled back.');
        });
    } catch (RuntimeException) {
        // The outer transaction undid the departure and everything the modules did.
    }

    expect($this->ana->refresh()->deactivated_at)->toBeNull();

    foreach (LEAVING_LOGS as $event) {
        Log::shouldNotHaveReceived('info', [$event, Mockery::any()]);
    }
});
