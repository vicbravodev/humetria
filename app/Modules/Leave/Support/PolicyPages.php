<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Actions\SavePolicy;
use App\Modules\Leave\Enums\RowKind;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyRow;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What the policy screens read: the law as the first card, the company's policies with what each gives the first year,
 * and a policy with its rows next to the floor of the law for each year. Days and percentages, never an amount.
 *
 * @phpstan-type Card array{id: string, name: string, premiumPercent: int, firstYearDays: int, fixedDays: int, assigned: int, archived: bool}
 */
final readonly class PolicyPages
{
    public const int PER_PAGE = 12;

    /** The years the form shows the floor of the law for; a longer service is capped at the last. */
    public const int FLOOR_YEARS = 60;

    /**
     * @return array{law: array{firstYearDays: int, premiumPercent: int}, policies: LengthAwarePaginator<int, Card>}
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
}
