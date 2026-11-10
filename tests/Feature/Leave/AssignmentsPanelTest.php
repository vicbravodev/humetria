<?php

declare(strict_types=1);

use App\Modules\Leave\Http\Controllers\AssignmentController;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(AssignmentController::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->policy = LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Corporativo']);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Gerente', 'level' => 'A']);
});

/**
 * @return array<string, mixed>
 */
function leaveAssignmentsProps(): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.policies.index', test()->tenant))->assertOk()->inertiaProps();
}

it('lists who has which policy: the company, each staff group and each job grade', function (): void {
    $other = LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sindical']);
    LeavePolicy::factory()->archived()->create(['tenant_id' => $this->tenant->id, 'name' => 'Vieja']);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id]);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $other->id, 'scope' => 'staff_group', 'staff_group' => 'unionized']);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $other->id, 'scope' => 'job_grade', 'job_grade_id' => $this->grade->id]);

    $props = leaveAssignmentsProps();

    expect($props['assignments'])->toBe(['default' => $this->policy->id, 'unionized' => $other->id, 'trusted' => null])
        ->and($props['policyOptions'])->toBe([['value' => $this->policy->id, 'label' => 'Corporativo'], ['value' => $other->id, 'label' => 'Sindical']])
        ->and($props['grades']['data'])->toBe([['id' => $this->grade->id, 'label' => 'Gerente · A', 'policyId' => $other->id]]);
});

it('assigns a policy to the company, to a staff group and to a job grade, and shows it', function (): void {
    $this->actingAs($this->hr)->put(route('tenant.vacations.assignments.update', $this->tenant), ['scope' => 'default', 'policy_id' => $this->policy->id])->assertRedirect();
    $this->actingAs($this->hr)->put(route('tenant.vacations.assignments.update', $this->tenant), ['scope' => 'staff_group', 'staff_group' => 'trusted', 'policy_id' => $this->policy->id])->assertRedirect();
    $this->actingAs($this->hr)->put(route('tenant.vacations.assignments.update', $this->tenant), ['scope' => 'job_grade', 'job_grade_id' => $this->grade->id, 'policy_id' => $this->policy->id])->assertRedirect();

    $props = leaveAssignmentsProps();

    expect($props['assignments'])->toBe(['default' => $this->policy->id, 'unionized' => null, 'trusted' => $this->policy->id])
        ->and($props['grades']['data'][0]['policyId'])->toBe($this->policy->id);
});

it('takes the policy away from a target when none is sent', function (): void {
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id]);

    $this->actingAs($this->hr)->put(route('tenant.vacations.assignments.update', $this->tenant), ['scope' => 'default', 'policy_id' => ''])->assertRedirect();

    expect(LeavePolicyAssignment::query()->count())->toBe(0);
});

it('sends the error of an unknown group, grade or archived policy back, in Spanish', function (string $case, string $field, string $message): void {
    $data = match ($case) {
        'group' => ['scope' => 'staff_group', 'staff_group' => 'bosses', 'policy_id' => $this->policy->id],
        'grade' => ['scope' => 'job_grade', 'job_grade_id' => JobGrade::factory()->create()->id, 'policy_id' => $this->policy->id],
        default => ['scope' => 'default', 'policy_id' => tap($this->policy)->update(['archived_at' => now()])->id],
    };

    $this->actingAs($this->hr)->put(route('tenant.vacations.assignments.update', $this->tenant), $data)->assertSessionHasErrors([$field => $message]);

    expect(LeavePolicyAssignment::query()->count())->toBe(0);
})->with([
    'a group that does not exist' => ['group', 'staff_group', 'Elige sindicalizados o de confianza.'],
    'a grade of another company' => ['grade', 'job_grade_id', 'Ese nivel de puesto no existe.'],
    'an archived policy' => ['archived', 'policy_id', 'Esa política está archivada.'],
]);

it('refuses a scope that does not exist', function (): void {
    $this->actingAs($this->hr)->put(route('tenant.vacations.assignments.update', $this->tenant), ['scope' => 'everybody', 'policy_id' => $this->policy->id])->assertSessionHasErrors('scope');
});

it('never assigns a policy of another company: 404', function (): void {
    $stranger = LeavePolicy::factory()->create();

    $this->actingAs($this->hr)->put(route('tenant.vacations.assignments.update', $this->tenant), ['scope' => 'default', 'policy_id' => $stranger->id])->assertNotFound();

    expect(LeavePolicyAssignment::query()->count())->toBe(0);
});

it('lets a viewer read the assignments but not change them', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($viewer)->put(route('tenant.vacations.assignments.update', $this->tenant), ['scope' => 'default', 'policy_id' => $this->policy->id])->assertForbidden();
    $this->actingAs($viewer)->get(route('tenant.vacations.grades.options', $this->tenant))->assertForbidden();
    $this->actingAs($viewer)->get(route('tenant.vacations.policies.index', $this->tenant))->assertOk();

    expect(LeavePolicyAssignment::query()->count())->toBe(0);
});

it('pages the grades that have a policy, 10 to a page', function (): void {
    JobGrade::factory()->count(12)->create(['tenant_id' => $this->tenant->id])->each(fn (JobGrade $grade) => LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $this->policy->id, 'scope' => 'job_grade', 'job_grade_id' => $grade->id]));

    $grades = leaveAssignmentsProps()['grades'];

    expect($grades['per_page'])->toBe(10)->and($grades['total'])->toBe(12)->and($grades['data'])->toHaveCount(10);
});

it('looks the job grades up on the server for the field that picks one', function (): void {
    JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Analista', 'level' => 'B']);
    JobGrade::factory()->create(['category' => 'Gerente', 'level' => 'Ajeno']);

    $found = $this->actingAs($this->hr)->getJson(route('tenant.vacations.grades.options', [$this->tenant, 'q' => 'GERÉN']))->assertOk()->json();

    expect($found)->toBe([['value' => $this->grade->id, 'label' => 'Gerente · A']]);
});
