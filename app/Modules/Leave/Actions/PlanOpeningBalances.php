<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\PeriodWindow;
use App\Modules\Leave\Support\PolicyResolver;
use App\Modules\Leave\Support\PolicyTerms;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Contracts\Seniority;

/**
 * Checks the rows of a file of opening balances without writing anything (criterio C-21): which periods can come in and,
 * row by row, why the others cannot. Only periods from before the company started: from that day on the daily command
 * grants them. Never a second period for a year of service, in the file or already loaded.
 */
final readonly class PlanOpeningBalances
{
    public function __construct(private Roster $roster, private Seniority $seniority, private PolicyResolver $policies) {}

    /**
     * @param  list<array<string, string>>  $rows  as OpeningCsv reads them
     * @return array{ready: list<array{line: int, employeeId: string, years: int, anniversary: string, days: int}>, rejected: list<array{line: int, reason: string}>}
     */
    public function handle(array $rows): array
    {
        $setting = LeaveSetting::query()->sole();
        $today = PeriodWindow::today();
        $ids = $this->roster->idsByEmployeeNumber(array_values(array_unique(array_map(fn (array $row): string => trim($row['numero_de_empleado'] ?? ''), $rows))));
        $people = array_values($ids);
        $starts = $this->seniority->startDatesFor($people);
        $terms = $this->policies->for($people);
        // Whoever the roster cannot place still has the law.
        $law = PolicyTerms::statutory($setting->statutory_work_week);
        $taken = [];

        foreach (LeaveEntitlement::query()->among('employee_id', $people)->get(['employee_id', 'years_of_service']) as $period) {
            $taken[$period->employee_id][$period->years_of_service] = 'already_loaded';
        }

        $ready = [];
        $rejected = [];

        foreach ($rows as $index => $row) {
            $given = $row['line'] ?? '';
            $line = ctype_digit($given) ? (int) $given : $index + 2;
            $employeeId = $ids[trim($row['numero_de_empleado'] ?? '')] ?? null;
            $start = $employeeId === null ? null : $starts[$employeeId] ?? null;

            if ($employeeId === null || $start === null) {
                $rejected[] = ['line' => $line, 'reason' => $employeeId === null ? 'unknown_employee' : 'no_start_date'];

                continue;
            }

            $year = trim($row['aniversario'] ?? '');
            $days = trim($row['dias_pendientes'] ?? '');
            $years = ctype_digit($year) ? (int) $year - $start->year : 0;
            $anniversary = $start->addYearsNoOverflow(max($years, 0));
            $reason = match (true) {
                $years < 1 || $anniversary->toDateString() > $today->toDateString() => 'not_an_anniversary',
                $anniversary->toDateString() >= $setting->tracking_since->toDateString() => 'after_tracking_start',
                ! ctype_digit($days) => 'invalid_days',
                (int) $days > array_sum(($terms[$employeeId] ?? $law)->daysFor($years, $anniversary)) => 'too_many_days',
                default => $taken[$employeeId][$years] ?? null,
            };

            if ($reason !== null) {
                $rejected[] = ['line' => $line, 'reason' => $reason];

                continue;
            }

            $taken[$employeeId][$years] = 'duplicate_in_file';
            $ready[] = ['line' => $line, 'employeeId' => $employeeId, 'years' => $years, 'anniversary' => $anniversary->toDateString(), 'days' => (int) $days];
        }

        return ['ready' => $ready, 'rejected' => $rejected];
    }
}
