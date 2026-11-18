<?php

declare(strict_types=1);

use App\Modules\Attendance\Contracts\AttendanceStanding;
use App\Modules\Attendance\Data\AttendanceSetup;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Support\CompanyAttendanceStanding;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(CompanyAttendanceStanding::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->standing = resolve(AttendanceStanding::class);
});

it('tells a company that has not started: no policy, nobody to schedule, nothing to correct', function (): void {
    workIn($this->a);

    expect($this->standing->current())->toEqual(new AttendanceSetup(false, 0, 0));
});

it('counts the people of the centers in use without a schedule and the corrections waiting for RH, never of another company', function (): void {
    AttendancePolicy::factory()->create(['tenant_id' => $this->a->id]);
    $center = WorkCenter::factory()->for($this->a)->create();
    $people = Employee::factory()->count(3)->create(['tenant_id' => $this->a->id, 'work_center_id' => $center->id]);
    AttendanceSchedule::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $people[0]->id]);
    // Someone who left, and someone of an archived center: neither is waited for.
    Employee::factory()->deactivated()->create(['tenant_id' => $this->a->id, 'work_center_id' => $center->id]);
    Employee::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => WorkCenter::factory()->for($this->a)->create(['archived_at' => now()])->id]);
    CorrectionRequest::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $people[1]->id]);
    CorrectionRequest::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $people[1]->id, 'status' => CorrectionStatus::Rejected, 'resolved_by' => App\Models\User::factory()->create()->id, 'resolved_at' => now(), 'note' => 'Ya estaba bien.']);
    Employee::factory()->count(2)->create(['tenant_id' => $this->b->id]);
    CorrectionRequest::factory()->count(2)->create(['tenant_id' => $this->b->id]);
    workIn($this->a);

    expect($this->standing->current())->toEqual(new AttendanceSetup(true, 2, 1));
});
