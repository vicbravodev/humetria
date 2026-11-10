<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\AssignPolicy;
use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\PolicyResolver;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

mutates(PolicyResolver::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->g3 = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'level' => 'G3']);
    [$this->p1, $this->p2, $this->p3] = collect(['P1', 'P2', 'P3'])->map(fn (string $name): LeavePolicy => LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]))->all();
    $this->unionizedG3 = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'staff_group' => StaffGroup::Unionized, 'job_grade_id' => $this->g3->id]);
    $this->trusted = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'staff_group' => StaffGroup::Trusted]);
    $this->plain = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
});

function assignAll(): void
{
    $assign = resolve(AssignPolicy::class);
    $assign->handle(PolicyScope::Default, test()->p1->id);
    $assign->handle(PolicyScope::StaffGroup, test()->p2->id, staffGroup: 'unionized');
    $assign->handle(PolicyScope::JobGrade, test()->p3->id, jobGradeId: test()->g3->id);
}

/**
 * @param  list<Employee>  $people
 * @return list<string|null>
 */
function resolvedNames(array $people): array
{
    $ids = array_map(fn (Employee $person): string => $person->id, $people);
    $terms = resolve(PolicyResolver::class)->for($ids);

    return array_map(fn (string $id): ?string => ($terms[$id] ?? null)?->name, $ids);
}

it('gives each person the most specific policy: job grade, then staff group, then the default', function (): void {
    assignAll();

    expect(resolvedNames([$this->unionizedG3, $this->trusted, $this->plain]))->toBe(['P3', 'P1', 'P1']);

    resolve(AssignPolicy::class)->handle(PolicyScope::JobGrade, null, jobGradeId: $this->g3->id);
    expect(resolvedNames([$this->unionizedG3, $this->trusted]))->toBe(['P2', 'P1']);

    resolve(AssignPolicy::class)->handle(PolicyScope::StaffGroup, null, staffGroup: 'unionized');
    expect(resolvedNames([$this->unionizedG3]))->toBe(['P1']);
});

it('does not give the policy of a job grade or of a staff group to who is in another', function (): void {
    $g4 = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'level' => 'G4']);
    $trustedG4 = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'staff_group' => StaffGroup::Trusted, 'job_grade_id' => $g4->id]);
    resolve(AssignPolicy::class)->handle(PolicyScope::StaffGroup, $this->p2->id, staffGroup: 'unionized');
    resolve(AssignPolicy::class)->handle(PolicyScope::JobGrade, $this->p3->id, jobGradeId: $this->g3->id);

    expect(resolvedNames([$trustedG4, $this->plain, $this->unionizedG3]))->toBe(['Ley', 'Ley', 'P3']);
});

it('falls back to the law, with the working week the company confirmed', function (?string $week, WorkWeek $expected): void {
    if ($week !== null) {
        LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'statutory_work_week' => $week]);
    }

    $terms = resolve(PolicyResolver::class)->for([$this->plain->id])[$this->plain->id];

    expect($terms->policyId)->toBeNull()
        ->and($terms->name)->toBe('Ley')
        ->and($terms->premiumPercent)->toBe(25)
        ->and($terms->workWeek)->toBe($expected);
})->with([
    'Monday to Saturday' => ['mon_sat', WorkWeek::MonSat],
    'Monday to Friday' => ['mon_fri', WorkWeek::MonFri],
    'a company that has not started: Monday to Friday' => [null, WorkWeek::MonFri],
]);

it('carries the terms of the policy: premium, week, advance, expiry, cap, tiers and fixed days', function (): void {
    $policy = LeavePolicy::factory()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Corporativo', 'premium_percent' => 40, 'work_week' => WorkWeek::MonSat,
        'allows_advance' => true, 'extra_expires_months' => 12, 'extra_carryover_cap_days' => 5,
    ]);
    LeavePolicyRow::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id, 'year_from' => 6, 'year_to' => 10, 'days' => 30]);
    LeavePolicyRow::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id]);
    LeavePolicyRow::factory()->fixed('Cumpleaños', 1)->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id]);
    LeavePolicyRow::factory()->fixed('Personales', 2)->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id]);
    resolve(AssignPolicy::class)->handle(PolicyScope::Default, $policy->id);

    $terms = resolve(PolicyResolver::class)->for([$this->plain->id])[$this->plain->id];

    expect([$terms->policyId, $terms->name, $terms->premiumPercent, $terms->workWeek, $terms->allowsAdvance, $terms->extraExpiresMonths, $terms->carryoverCap, $terms->fixedDays])
        ->toBe([$policy->id, 'Corporativo', 40, WorkWeek::MonSat, true, 12, 5, 3])
        ->and($terms->tiers)->toBe([['from' => 1, 'to' => 5, 'days' => 20], ['from' => 6, 'to' => 10, 'days' => 30]]);
});

it('leaves out whoever is not of the company, and answers nothing for nobody', function (): void {
    assignAll();
    $ofOther = Employee::factory()->create();

    expect(array_keys(resolve(PolicyResolver::class)->for([$ofOther->id, $this->plain->id])))->toBe([$this->plain->id])
        ->and(resolve(PolicyResolver::class)->for([]))->toBe([]);
});

it('does not use the assignments of another company', function (): void {
    assignAll();
    $b = Tenant::factory()->create();
    $ofB = Employee::factory()->create(['tenant_id' => $b->id, 'staff_group' => StaffGroup::Unionized]);

    workIn($b);
    expect(resolvedNames([$ofB]))->toBe(['Ley']);
});

it('resolves fifty people with the queries of five', function (): void {
    assignAll();
    $count = function (int $people): int {
        $ids = Employee::factory()->count($people)->create(['tenant_id' => $this->tenant->id, 'staff_group' => StaffGroup::Unionized, 'job_grade_id' => $this->g3->id])->map(fn (Employee $person): string => $person->id)->all();
        DB::flushQueryLog();
        DB::enableQueryLog();
        expect(resolve(PolicyResolver::class)->for($ids))->toHaveCount($people);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($count(50))->toBe($count(5));
});
