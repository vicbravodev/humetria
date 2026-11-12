<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Enums\RowKind;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Organization\Contracts\Roster;
use LogicException;

/**
 * The terms each person's vacations follow (criterio C-15). The most specific assignment wins: the one of their job
 * grade, then the one of their staff group, then the default of the company; with none, the law.
 */
final readonly class PolicyResolver
{
    public function __construct(private Roster $roster) {}

    /**
     * The same queries for one person as for a thousand. Whoever is not of the company is left out.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, PolicyTerms> by employee id
     */
    public function for(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $byGrade = [];
        $byGroup = [];
        $default = PolicyTerms::statutory(LeaveSetting::query()->first()->statutory_work_week ?? WorkWeek::MonFri);

        foreach (LeavePolicyAssignment::query()->with('policy.rows')->get() as $assignment) {
            // The foreign key makes the policy required; a missing one is broken data, never the law.
            $terms = self::termsOf($assignment->policy ?? throw new LogicException("Assignment [{$assignment->id}] has no policy."));

            match ($assignment->scope) {
                PolicyScope::JobGrade => $byGrade[$assignment->job_grade_id ?? ''] = $terms,
                PolicyScope::StaffGroup => $byGroup[$assignment->staff_group ?? ''] = $terms,
                PolicyScope::Default => $default = $terms,
            };
        }

        $resolved = [];

        foreach ($this->roster->findMany($employeeIds) as $id => $person) {
            $resolved[$id] = $byGrade[$person->jobGradeId ?? ''] ?? $byGroup[$person->staffGroup ?? ''] ?? $default;
        }

        return $resolved;
    }

    public static function termsOf(LeavePolicy $policy): PolicyTerms
    {
        $tiers = [];
        $fixedDays = 0;

        foreach ($policy->rows->sortBy('year_from') as $row) {
            if ($row->kind === RowKind::Fixed) {
                $fixedDays += $row->days;
            } elseif ($row->year_from !== null && $row->year_to !== null) {
                $tiers[] = ['from' => $row->year_from, 'to' => $row->year_to, 'days' => $row->days];
            }
        }

        return new PolicyTerms($policy->id, $policy->name, $policy->premium_percent, $policy->work_week, $policy->allows_advance, $policy->extra_expires_months, $policy->extra_carryover_cap_days, $tiers, $fixedDays);
    }
}
