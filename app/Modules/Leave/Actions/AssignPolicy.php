<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Says which policy goes to a target (criterio C-15): the default of the company, a staff group or a job grade. Without
 * a policy, the target loses its assignment and falls to the next one, down to the law. Periods already granted do not
 * change.
 */
final readonly class AssignPolicy
{
    private const array STAFF_GROUPS = ['unionized', 'trusted'];

    public function __construct(private CurrentTenant $tenant, private JobGrades $jobGrades, private RecordAudit $audit) {}

    /**
     * @throws ValidationException
     */
    public function handle(PolicyScope $scope, ?string $policyId, ?string $staffGroup = null, ?string $jobGradeId = null): void
    {
        $staffGroup = $scope === PolicyScope::StaffGroup ? $staffGroup : null;
        $jobGradeId = $scope === PolicyScope::JobGrade ? $jobGradeId : null;

        if ($scope === PolicyScope::StaffGroup && ! in_array($staffGroup, self::STAFF_GROUPS, true)) {
            throw ValidationException::withMessages(['staff_group' => 'Elige sindicalizados o de confianza.']);
        }

        if ($scope === PolicyScope::JobGrade && ($jobGradeId === null || $this->jobGrades->find($jobGradeId) === null)) {
            throw ValidationException::withMessages(['job_grade_id' => 'Ese nivel de puesto no existe.']);
        }

        $target = ['scope' => $scope, 'staff_group' => $staffGroup, 'job_grade_id' => $jobGradeId];
        $context = ['scope' => $scope->value, 'policy_id' => $policyId, 'staff_group' => $staffGroup, 'job_grade_id' => $jobGradeId];

        DB::transaction(function () use ($policyId, $target, $context): void {
            if ($policyId === null) {
                LeavePolicyAssignment::query()->where($target)->delete();
            } else {
                // Held until the end: archiving it and assigning it never both pass their checks.
                $policy = LeavePolicy::query()->lockForUpdate()->findOrFail($policyId);

                if ($policy->archived_at !== null) {
                    throw ValidationException::withMessages(['policy_id' => 'Esa política está archivada.']);
                }

                LeavePolicyAssignment::query()->updateOrCreate($target, ['tenant_id' => $this->tenant->id(), 'leave_policy_id' => $policy->id]);
            }

            $this->audit->handle('leave.policy_assigned', metadata: $context);
        });

        Log::info('leave.policy_assigned', $context);
    }
}
