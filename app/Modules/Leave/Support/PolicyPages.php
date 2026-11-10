<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Actions\SavePolicy;
use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Enums\RowKind;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Organization\Contracts\JobGrades;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What the policy screens read: the law as the first card, the company's policies with what each gives the first year,
 * and a policy with its rows next to the floor of the law for each year. Days and percentages, never an amount.
 *
 * @phpstan-type GradeRow array{id: string, label: string, policyId: string}
 * @phpstan-type Card array{id: string, name: string, premiumPercent: int, firstYearDays: int, fixedDays: int, assigned: int, archived: bool}
 */
final readonly class PolicyPages
{
    public function __construct(private JobGrades $jobGrades) {}

    public const int PER_PAGE = 12;

    public const int GRADES_PER_PAGE = 10;

    /** The years the form shows the floor of the law for; a longer service is capped at the last. */
    public const int FLOOR_YEARS = 60;

    /**
     * @return array{law: array{firstYearDays: int, premiumPercent: int}, policies: LengthAwarePaginator<int, Card>, assignments: array{default: string|null, unionized: string|null, trusted: string|null}, policyOptions: list<array{value: string, label: string}>, grades: LengthAwarePaginator<int, GradeRow>}
     */
    public function index(): array
    {
        $policies = LeavePolicy::query()->with(['rows', 'assignments'])->orderByRaw('archived_at is not null')->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();

        return [
            'law' => ['firstYearDays' => StatutoryDays::current(1), 'premiumPercent' => SavePolicy::PREMIUM_FLOOR],
            'policies' => $policies->through(fn (LeavePolicy $policy): array => [
                'id' => $policy->id,
                'name' => $policy->name,
                'premiumPercent' => $policy->premium_percent,
                'firstYearDays' => self::firstYearDays($policy),
                'fixedDays' => array_sum($policy->rows->where('kind', RowKind::Fixed)->map(fn (LeavePolicyRow $row): int => $row->days)->all()),
                'assigned' => $policy->assignments->count(),
                'archived' => $policy->archived_at !== null,
            ]),
            'assignments' => $this->assignments(),
            'policyOptions' => array_values(LeavePolicy::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name'])->map(fn (LeavePolicy $policy): array => ['value' => $policy->id, 'label' => $policy->name])->all()),
            'grades' => $this->grades(),
        ];
    }

    /**
     * @return array{policy: array{id: string, name: string, premiumPercent: int, workWeek: string, allowsAdvance: bool, extraExpiresMonths: int|null, extraCarryoverCapDays: int|null, rows: list<array{kind: string, label: string|null, yearFrom: int|null, yearTo: int|null, days: int}>}|null, floors: list<int>}
     */
    public function form(?LeavePolicy $policy): array
    {
        return [
            'policy' => $policy === null ? null : [
                'id' => $policy->id,
                'name' => $policy->name,
                'premiumPercent' => $policy->premium_percent,
                'workWeek' => $policy->work_week->value,
                'allowsAdvance' => $policy->allows_advance,
                'extraExpiresMonths' => $policy->extra_expires_months,
                'extraCarryoverCapDays' => $policy->extra_carryover_cap_days,
                'rows' => array_values($policy->rows->sortBy([['kind', 'asc'], ['year_from', 'asc']])->map(fn (LeavePolicyRow $row): array => [
                    'kind' => $row->kind->value, 'label' => $row->label, 'yearFrom' => $row->year_from, 'yearTo' => $row->year_to, 'days' => $row->days,
                ])->all()),
            ],
            'floors' => array_map(StatutoryDays::current(...), range(1, self::FLOOR_YEARS)),
        ];
    }

    /** What the policy gives in the first year: its tier for it, or the law when no tier reaches it. */
    private static function firstYearDays(LeavePolicy $policy): int
    {
        $tier = $policy->rows->first(fn (LeavePolicyRow $row): bool => $row->kind === RowKind::Tier && ($row->year_from ?? 0) <= 1 && ($row->year_to ?? 0) >= 1);

        return $tier->days ?? StatutoryDays::current(1);
    }

    /**
     * The policy of the company and of each staff group; null where there is none and the law follows.
     *
     * @return array{default: string|null, unionized: string|null, trusted: string|null}
     */
    private function assignments(): array
    {
        $given = ['default' => null, 'unionized' => null, 'trusted' => null];

        foreach (LeavePolicyAssignment::query()->whereIn('scope', [PolicyScope::Default, PolicyScope::StaffGroup])->get() as $assignment) {
            $key = $assignment->scope === PolicyScope::Default ? 'default' : $assignment->staff_group;

            if ($key === 'default' || $key === 'unionized' || $key === 'trusted') {
                $given[$key] = $assignment->leave_policy_id;
            }
        }

        return $given;
    }

    /**
     * The job grades that have a policy of their own, a page at a time, with the name of each.
     *
     * @return LengthAwarePaginator<int, GradeRow>
     */
    private function grades(): LengthAwarePaginator
    {
        $page = LeavePolicyAssignment::query()->where('scope', PolicyScope::JobGrade)->orderBy('created_at')->orderBy('id')->paginate(self::GRADES_PER_PAGE, pageName: 'grades')->withQueryString();
        $names = $this->jobGrades->findMany(array_values(array_filter(array_map(fn (LeavePolicyAssignment $assignment): ?string => $assignment->job_grade_id, $page->items()))));

        return $page->through(function (LeavePolicyAssignment $assignment) use ($names): array {
            $id = $assignment->job_grade_id ?? '';
            $grade = $names[$id] ?? null;

            return [
                'id' => $id,
                'label' => $grade === null ? 'Nivel sin nombre' : "{$grade->category} · {$grade->level}",
                'policyId' => $assignment->leave_policy_id,
            ];
        });
    }
}
