<?php

declare(strict_types=1);

use App\Modules\Compensation\Contracts\CompensationStanding;
use App\Modules\Compensation\Data\ContractStanding;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\CompanyCompensationStanding;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;

mutates(CompanyCompensationStanding::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->standing = resolve(CompensationStanding::class);
});

it('tells a company with nothing loaded', function (): void {
    workIn($this->a);

    expect($this->standing->current())->toEqual(new ContractStanding(0, 0, 0, 0));
});

it('counts the people the cost covers, the categories without a salary and the active scenarios, never of another company', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->a->id]);
    GradeSalary::factory()->create(['tenant_id' => $this->a->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);
    Employee::factory()->count(2)->create(['tenant_id' => $this->a->id, 'job_grade_id' => $grade->id, 'hired_on' => '2025-01-01', 'staff_group' => 'unionized', 'state' => 'JAL']);
    Employee::factory()->create(['tenant_id' => $this->a->id, 'job_grade_id' => null]);
    JobGrade::factory()->create(['tenant_id' => $this->a->id]);
    Scenario::factory()->create(['tenant_id' => $this->a->id]);
    Scenario::factory()->archived()->create(['tenant_id' => $this->a->id]);
    Employee::factory()->count(4)->create(['tenant_id' => $this->b->id]);
    Scenario::factory()->count(2)->create(['tenant_id' => $this->b->id]);
    workIn($this->a);

    expect($this->standing->current())->toEqual(new ContractStanding(3, 2, 1, 1));
});
