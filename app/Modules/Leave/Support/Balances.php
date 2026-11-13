<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use Carbon\CarbonImmutable;

/**
 * What each person has left of their vacations. A balance is never stored: it is the sum of the entries of the ledger,
 * by period and tranche. Nothing is taken off for being past a window (criterios C-09 and C-11).
 */
final class Balances
{
    /**
     * The periods of each person with what is left of them, from the oldest to the newest; one query for any number of
     * people. Whoever has no periods is not in the answer.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, list<PeriodBalance>> by employee id
     */
    public static function of(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $rows = LeaveEntitlement::query()
            ->among('leave_entitlements.employee_id', $employeeIds)
            ->leftJoin('leave_entries', 'leave_entries.leave_entitlement_id', '=', 'leave_entitlements.id')
            ->groupBy('leave_entitlements.id')
            ->orderBy('leave_entitlements.anniversary_on')
            ->orderBy('leave_entitlements.id')
            ->toBase()
            ->selectRaw(<<<'SQL'
                leave_entitlements.id, leave_entitlements.employee_id, leave_entitlements.anniversary_on,
                leave_entitlements.enjoy_by, leave_entitlements.prescribes_on,
                coalesce(sum(leave_entries.days) filter (where leave_entries.tranche = 'statutory'), 0)::int as statutory_left,
                coalesce(sum(leave_entries.days) filter (where leave_entries.tranche = 'extra'), 0)::int as extra_left,
                (leave_entitlements.statutory_days + leave_entitlements.extra_days)::int as granted
            SQL)
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            // The checks only narrow the types of a raw row: the database always gives strings and integers.
            if (is_string($row->id) && is_string($row->employee_id) && is_string($row->anniversary_on) && is_string($row->enjoy_by) && is_string($row->prescribes_on) && is_int($row->statutory_left) && is_int($row->extra_left) && is_int($row->granted)) { // @pest-mutate-ignore: BooleanAndToBooleanOr
                $balances[$row->employee_id][] = new PeriodBalance(
                    $row->id,
                    CarbonImmutable::parse($row->anniversary_on),
                    CarbonImmutable::parse($row->enjoy_by),
                    CarbonImmutable::parse($row->prescribes_on),
                    $row->statutory_left,
                    $row->extra_left,
                    $row->granted,
                );
            }
        }

        return $balances;
    }

    /**
     * The days each person was given in advance (criterio C-17): the sum of their entries with no period, zero or below.
     * Whoever has none is not in the answer.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, int> by employee id
     */
    public static function advanced(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $rows = LeaveEntry::query()
            ->among('employee_id', $employeeIds)
            ->whereNull('leave_entitlement_id')
            ->groupBy('employee_id')
            ->orderBy('employee_id')
            ->toBase()
            ->selectRaw('employee_id, sum(days)::int as days')
            ->get();

        $advanced = [];

        foreach ($rows as $row) {
            if (is_string($row->employee_id) && is_int($row->days)) { // @pest-mutate-ignore: BooleanAndToBooleanOr
                $advanced[$row->employee_id] = $row->days;
            }
        }

        return $advanced;
    }

    /**
     * The working days of the vacations each person asked for that nobody has answered: they take nothing off the balance
     * yet. A leave (with a type) uses no vacation days, so it is not here. Whoever has none is not in the answer.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, int> by employee id
     */
    public static function waiting(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $rows = LeaveRequest::query()
            ->among('employee_id', $employeeIds)
            ->whereNull('leave_type_id')
            ->where('status', RequestStatus::Requested)
            ->groupBy('employee_id')
            ->orderBy('employee_id')
            ->toBase()
            ->selectRaw('employee_id, sum(working_days)::int as days')
            ->get();

        $waiting = [];

        foreach ($rows as $row) {
            if (is_string($row->employee_id) && is_int($row->days)) { // @pest-mutate-ignore: BooleanAndToBooleanOr
                $waiting[$row->employee_id] = $row->days;
            }
        }

        return $waiting;
    }

    /**
     * The days a person can still ask for: what is left of all their periods, less what they were given in advance.
     */
    public static function available(string $employeeId): int
    {
        $left = array_sum(array_map(fn (PeriodBalance $period): int => $period->left(), self::of([$employeeId])[$employeeId] ?? []));

        return $left + (self::advanced([$employeeId])[$employeeId] ?? 0);
    }
}
