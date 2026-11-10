<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Leave\Actions\ArchivePolicy;
use App\Modules\Leave\Actions\AssignPolicy;
use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

mutates(AssignPolicy::class, ArchivePolicy::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->policy = LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * @return array<string, list<string>>
 */
function assignmentErrors(Closure $act): array
{
    try {
        $act();
    } catch (ValidationException $refused) {
        return $refused->errors();
    }

    return [];
}

it('assigns a policy as the default, to a staff group and to a job grade', function (): void {
    $assign = resolve(AssignPolicy::class);

    $assign->handle(PolicyScope::Default, $this->policy->id);
    $assign->handle(PolicyScope::StaffGroup, $this->policy->id, staffGroup: 'unionized');
    $assign->handle(PolicyScope::StaffGroup, $this->policy->id, staffGroup: 'trusted');
    $assign->handle(PolicyScope::JobGrade, $this->policy->id, jobGradeId: $this->grade->id);

    expect(LeavePolicyAssignment::query()->orderBy('scope')->orderBy('staff_group')->get()->map->only(['tenant_id', 'leave_policy_id', 'scope', 'staff_group', 'job_grade_id'])->all())->toBe([
        ['tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id, 'scope' => PolicyScope::Default, 'staff_group' => null, 'job_grade_id' => null],
        ['tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id, 'scope' => PolicyScope::JobGrade, 'staff_group' => null, 'job_grade_id' => $this->grade->id],
        ['tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id, 'scope' => PolicyScope::StaffGroup, 'staff_group' => 'trusted', 'job_grade_id' => null],
        ['tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id, 'scope' => PolicyScope::StaffGroup, 'staff_group' => 'unionized', 'job_grade_id' => null],
    ]);
});

it('replaces the policy of a target instead of adding a second one', function (): void {
    $other = LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id]);
    $otherGrade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);
    $assign = resolve(AssignPolicy::class);
    $assign->handle(PolicyScope::Default, $this->policy->id);
    $assign->handle(PolicyScope::JobGrade, $this->policy->id, jobGradeId: $this->grade->id);
    $assign->handle(PolicyScope::JobGrade, $this->policy->id, jobGradeId: $otherGrade->id);
    $assign->handle(PolicyScope::StaffGroup, $this->policy->id, staffGroup: 'trusted');
    $assign->handle(PolicyScope::StaffGroup, $this->policy->id, staffGroup: 'unionized');

    $assign->handle(PolicyScope::Default, $other->id);
    $assign->handle(PolicyScope::JobGrade, $other->id, jobGradeId: $this->grade->id);
    $assign->handle(PolicyScope::StaffGroup, $other->id, staffGroup: 'trusted');

    expect(LeavePolicyAssignment::query()->count())->toBe(5)
        ->and(LeavePolicyAssignment::query()->where('scope', PolicyScope::Default)->sole()->leave_policy_id)->toBe($other->id)
        ->and(LeavePolicyAssignment::query()->where('job_grade_id', $this->grade->id)->sole()->leave_policy_id)->toBe($other->id)
        ->and(LeavePolicyAssignment::query()->where('job_grade_id', $otherGrade->id)->sole()->leave_policy_id)->toBe($this->policy->id)
        ->and(LeavePolicyAssignment::query()->where('staff_group', 'trusted')->sole()->leave_policy_id)->toBe($other->id)
        ->and(LeavePolicyAssignment::query()->where('staff_group', 'unionized')->sole()->leave_policy_id)->toBe($this->policy->id);
});

it('removes only the assignment of the target when given no policy', function (): void {
    $otherGrade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);
    $assign = resolve(AssignPolicy::class);
    $assign->handle(PolicyScope::Default, $this->policy->id);
    $assign->handle(PolicyScope::StaffGroup, $this->policy->id, staffGroup: 'unionized');
    $assign->handle(PolicyScope::StaffGroup, $this->policy->id, staffGroup: 'trusted');
    $assign->handle(PolicyScope::JobGrade, $this->policy->id, jobGradeId: $this->grade->id);
    $assign->handle(PolicyScope::JobGrade, $this->policy->id, jobGradeId: $otherGrade->id);

    $assign->handle(PolicyScope::StaffGroup, null, staffGroup: 'unionized');
    $assign->handle(PolicyScope::JobGrade, null, jobGradeId: $this->grade->id);

    expect(LeavePolicyAssignment::query()->orderBy('scope')->get()->map(fn (LeavePolicyAssignment $kept): array => [$kept->scope, $kept->staff_group, $kept->job_grade_id])->all())->toBe([
        [PolicyScope::Default, null, null],
        [PolicyScope::JobGrade, null, $otherGrade->id],
        [PolicyScope::StaffGroup, 'trusted', null],
    ]);

    $assign->handle(PolicyScope::Default, null);
    expect(LeavePolicyAssignment::query()->where('scope', PolicyScope::Default)->count())->toBe(0)
        ->and(LeavePolicyAssignment::query()->count())->toBe(2);
});

it('keeps only what the scope needs', function (): void {
    resolve(AssignPolicy::class)->handle(PolicyScope::Default, $this->policy->id, 'unionized', $this->grade->id);
    resolve(AssignPolicy::class)->handle(PolicyScope::StaffGroup, $this->policy->id, 'unionized', $this->grade->id);
    resolve(AssignPolicy::class)->handle(PolicyScope::JobGrade, $this->policy->id, 'unionized', $this->grade->id);

    expect(LeavePolicyAssignment::query()->orderBy('scope')->get()->map(fn (LeavePolicyAssignment $kept): array => [$kept->staff_group, $kept->job_grade_id])->all())
        ->toBe([[null, null], [null, $this->grade->id], ['unionized', null]]);
});

it('refuses an archived policy', function (): void {
    $archived = LeavePolicy::factory()->archived()->create(['tenant_id' => $this->tenant->id]);

    expect(assignmentErrors(fn () => resolve(AssignPolicy::class)->handle(PolicyScope::Default, $archived->id)))->toBe(['policy_id' => ['Esa política está archivada.']])
        ->and(LeavePolicyAssignment::query()->count())->toBe(0);
});

it('refuses a staff group or a job grade it does not know', function (PolicyScope $scope, ?string $staffGroup, ?string $jobGradeId, array $expected): void {
    expect(assignmentErrors(fn () => resolve(AssignPolicy::class)->handle($scope, $this->policy->id, $staffGroup, $jobGradeId)))->toBe($expected)
        ->and(LeavePolicyAssignment::query()->count())->toBe(0);
})->with([
    'no group' => [PolicyScope::StaffGroup, null, null, ['staff_group' => ['Elige sindicalizados o de confianza.']]],
    'unknown group' => [PolicyScope::StaffGroup, 'directors', null, ['staff_group' => ['Elige sindicalizados o de confianza.']]],
    'no grade' => [PolicyScope::JobGrade, null, null, ['job_grade_id' => ['Ese nivel de puesto no existe.']]],
    'unknown grade' => [PolicyScope::JobGrade, null, '0b0e7a52-1111-4222-8333-444455556666', ['job_grade_id' => ['Ese nivel de puesto no existe.']]],
]);

it('refuses the job grade and the policy of another company', function (): void {
    $gradeOfOther = JobGrade::factory()->create();
    $policyOfOther = LeavePolicy::factory()->create();

    expect(assignmentErrors(fn () => resolve(AssignPolicy::class)->handle(PolicyScope::JobGrade, $this->policy->id, jobGradeId: $gradeOfOther->id)))->toBe(['job_grade_id' => ['Ese nivel de puesto no existe.']])
        ->and(fn () => resolve(AssignPolicy::class)->handle(PolicyScope::Default, $policyOfOther->id))->toThrow(ModelNotFoundException::class)
        ->and(LeavePolicyAssignment::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('logs and audits each assignment and each removal, with ids only', function (): void {
    Log::spy();

    resolve(AssignPolicy::class)->handle(PolicyScope::JobGrade, $this->policy->id, jobGradeId: $this->grade->id);
    resolve(AssignPolicy::class)->handle(PolicyScope::JobGrade, null, jobGradeId: $this->grade->id);

    $assigned = ['scope' => 'job_grade', 'policy_id' => $this->policy->id, 'staff_group' => null, 'job_grade_id' => $this->grade->id];
    Log::shouldHaveReceived('info')->with('leave.policy_assigned', $assigned)->once();
    Log::shouldHaveReceived('info')->with('leave.policy_assigned', [...$assigned, 'policy_id' => null])->once();
    expect(AuditEntry::query()->where('event', 'leave.policy_assigned')->orderBy('id')->get()->map(fn (AuditEntry $entry): mixed => $entry->metadata)->all())
        ->toBe([$assigned, [...$assigned, 'policy_id' => null]]);
});

it('archives a policy nobody is assigned', function (): void {
    $this->travelTo('2026-11-10 18:00:00');
    Log::spy();

    resolve(ArchivePolicy::class)->handle($this->policy->id);

    expect($this->policy->refresh()->archived_at?->toDateTimeString())->toBe('2026-11-10 18:00:00')
        ->and(AuditEntry::query()->where('event', 'leave.policy_archived')->sole()->subject_id)->toBe($this->policy->id);
    Log::shouldHaveReceived('info')->once()->with('leave.policy_archived', ['policy_id' => $this->policy->id]);
});

it('does not archive a policy while it is assigned', function (): void {
    $free = LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id]);
    resolve(AssignPolicy::class)->handle(PolicyScope::StaffGroup, $this->policy->id, staffGroup: 'trusted');
    Log::spy();

    expect(assignmentErrors(fn () => resolve(ArchivePolicy::class)->handle($this->policy->id)))->toBe(['policy_id' => ['Está asignada: cambia primero a quién le toca.']])
        ->and($this->policy->refresh()->archived_at)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'leave.policy_archived')->count())->toBe(0);
    Log::shouldNotHaveReceived('info');

    // The assignment of another policy does not hold this one.
    resolve(ArchivePolicy::class)->handle($free->id);
    expect($free->refresh()->archived_at)->not->toBeNull();
});

it('keeps the day a policy was archived when archived again, and does not archive the one of another company', function (): void {
    $this->travelTo('2026-11-10 18:00:00');
    resolve(ArchivePolicy::class)->handle($this->policy->id);
    $this->travelTo('2026-12-01 18:00:00');
    resolve(ArchivePolicy::class)->handle($this->policy->id);
    $ofOther = LeavePolicy::factory()->create();

    expect($this->policy->refresh()->archived_at?->toDateString())->toBe('2026-11-10')
        ->and(AuditEntry::query()->where('event', 'leave.policy_archived')->count())->toBe(1)
        ->and(fn () => resolve(ArchivePolicy::class)->handle($ofOther->id))->toThrow(ModelNotFoundException::class)
        ->and($ofOther->refresh()->archived_at)->toBeNull();
});

it('rejects in the database an assignment whose columns do not match its scope', function (array $attributes): void {
    $row = ['id' => Str::uuid()->toString(), 'tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id, 'staff_group' => null, 'job_grade_id' => null];

    expect(fn () => DB::table('leave_policy_assignments')->insert([...$row, ...$attributes]))->toThrow(QueryException::class, 'leave_policy_assignments_scope_shape');
})->with([
    'default with a group' => [['scope' => 'default', 'staff_group' => 'trusted']],
    'group without one' => [['scope' => 'staff_group']],
    'group it does not know' => [['scope' => 'staff_group', 'staff_group' => 'directors']],
    'grade without one' => [['scope' => 'job_grade']],
    'unknown scope' => [['scope' => 'area']],
]);

it('rejects in the database a second assignment for the same target', function (array $attributes, string $index): void {
    $attributes = array_map(fn (?string $value): ?string => $value === 'grade' ? $this->grade->id : $value, $attributes);
    $row = ['tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id, 'staff_group' => null, 'job_grade_id' => null, ...$attributes];
    DB::table('leave_policy_assignments')->insert(['id' => Str::uuid()->toString(), ...$row]);

    expect(fn () => DB::table('leave_policy_assignments')->insert(['id' => Str::uuid()->toString(), ...$row]))->toThrow(QueryException::class, $index);
})->with([
    [['scope' => 'default'], 'leave_policy_assignments_one_default'],
    [['scope' => 'staff_group', 'staff_group' => 'trusted'], 'leave_policy_assignments_one_per_staff_group'],
    [['scope' => 'job_grade', 'job_grade_id' => 'grade'], 'leave_policy_assignments_one_per_job_grade'],
]);

it('rejects in the database the policy or the job grade of another company', function (array $attributes, string $constraint): void {
    $row = ['id' => Str::uuid()->toString(), 'tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id, 'scope' => 'default', 'staff_group' => null, 'job_grade_id' => null];
    $foreign = ['policy' => fn (): string => LeavePolicy::factory()->create()->id, 'grade' => fn (): string => JobGrade::factory()->create()->id];
    $attributes = array_map(fn (string $value): string => array_key_exists($value, $foreign) ? $foreign[$value]() : $value, $attributes);

    expect(fn () => DB::table('leave_policy_assignments')->insert([...$row, ...$attributes]))->toThrow(QueryException::class, $constraint);
})->with([
    [['leave_policy_id' => 'policy'], 'leave_policy_assignments_leave_policy_id_tenant_id_foreign'],
    [['scope' => 'job_grade', 'job_grade_id' => 'grade'], 'leave_policy_assignments_job_grade_id_tenant_id_foreign'],
]);

it('does not read the assignments of another company with a query', function (): void {
    $b = Tenant::factory()->create();
    $ofB = LeavePolicyAssignment::factory()->create(['tenant_id' => $b->id]);

    expect(LeavePolicyAssignment::query()->count())->toBe(0)
        ->and(LeavePolicyAssignment::query()->find($ofB->id))->toBeNull()
        ->and(LeavePolicyAssignment::query()->whereKey($ofB->id)->delete())->toBe(0);

    workIn($b);
    expect(LeavePolicyAssignment::query()->count())->toBe(1);
});
