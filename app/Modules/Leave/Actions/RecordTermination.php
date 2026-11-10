<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Leave\Support\PeriodWindow;
use App\Modules\Leave\Support\PolicyResolver;
use App\Modules\Leave\Support\PolicyTerms;
use App\Modules\Leave\Support\ProportionalDays;
use App\Modules\Organization\Contracts\Seniority;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * What a person is owed, in days, the day the relationship ends (LFT art. 79; criterio C-07): what is left of their
 * periods, less what they were given in advance, and the part of the year in course by their policy, counted from the last
 * anniversary or, with none yet, from the start of their seniority. Never an amount. Nothing is taken from the ledger here:
 * that is SettleTermination. One left open before is voided: only the last one counts.
 */
final readonly class RecordTermination
{
    public function __construct(private CurrentTenant $tenant, private Seniority $seniority, private PolicyResolver $policies) {}

    /**
     * @return LeaveTermination|null null when the company does not use Vacaciones or the person has no start date
     */
    public function handle(string $employeeId): ?LeaveTermination
    {
        $setting = LeaveSetting::query()->first();
        $start = $this->seniority->startDatesFor([$employeeId])[$employeeId] ?? null;

        if ($setting === null || $start === null) {
            return null;
        }

        $today = PeriodWindow::today();
        $anniversaries = PeriodWindow::anniversaries($start, $start, $today);
        $years = count($anniversaries);
        $terms = $this->policies->for([$employeeId])[$employeeId] ?? PolicyTerms::statutory($setting->statutory_work_week);
        // Whoever has not started yet has no year in course.
        $from = min($today, $anniversaries[$years] ?? $start->startOfDay());

        $termination = DB::transaction(function () use ($employeeId, $today, $from, $years, $start, $terms): LeaveTermination {
            EmployeeLock::of($employeeId);
            LeaveTermination::query()->where('employee_id', $employeeId)->whereNull('settled_at')->whereNull('voided_at')->update(['voided_at' => now()]);

            return LeaveTermination::query()->create([
                'tenant_id' => $this->tenant->id(),
                'employee_id' => $employeeId,
                'terminated_on' => $today->toDateString(),
                'pending_days' => Balances::available($employeeId),
                'proportional_days' => ProportionalDays::for($from, $today, array_sum($terms->daysFor($years + 1, $start->addYearsNoOverflow($years + 1)))),
            ]);
        });

        Log::info('leave.termination_recorded', [
            'termination_id' => $termination->id, 'employee_id' => $employeeId, 'pending_days' => $termination->pending_days, 'proportional_days' => $termination->proportional_days,
        ]);

        return $termination;
    }
}
