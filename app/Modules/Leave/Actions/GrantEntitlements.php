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
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily grant, for every company that uses Vacaciones: each active person gets the period of every anniversary from
 * the day the company started (criterio C-21) up to today that they do not have yet. So a day it did not run is made up
 * the next one, and running it again changes nothing. Anniversaries before the start are never granted: they come in as
 * opening balances. Whoever has no start date gets nothing and is counted, so RH completes it.
 */
final readonly class GrantEntitlements
{
    public function __construct(
        private Companies $companies,
        private WorkCenters $workCenters,
        private Roster $roster,
        private Seniority $seniority,
        private PolicyResolver $policies,
        private GrantEntitlement $grant,
        private ExpireExtraDays $expire,
    ) {}

    /**
     * @return int how many periods were granted
     */
    public function handle(): int
    {
        $granted = 0;

        foreach (LeaveSetting::query()->withoutGlobalScopes()->get(['tenant_id']) as $company) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($company, &$granted): void {
                    $granted += $this->grantIn($company->tenant_id);
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others from getting their periods.
                report($failure);
            }
        }

        Log::info('leave.entitlements_granted', ['granted' => $granted]);

        return $granted;
    }

    /**
     * The active people of the current company with no hire date nor recognized seniority: nothing can be granted to
     * them until RH completes it.
     *
     * @return list<string>
     */
    public function missingStartDate(): array
    {
        $active = $this->activeIds();

        return array_values(array_diff($active, array_keys($this->seniority->startDatesFor($active))));
    }

    private function grantIn(string $tenantId): int
    {
        if ($this->companies->enter($tenantId)->blocked) {
            return 0;
        }

        $setting = LeaveSetting::query()->sole();
        $today = PeriodWindow::today();
        $active = $this->activeIds();
        $starts = $this->seniority->startDatesFor($active);
        $terms = $this->policies->for(array_keys($starts));
        $had = [];

        // ponytail: reads every period of the company once a day; narrow to the people with an anniversary due if it grows.
        foreach (LeaveEntitlement::query()->get(['employee_id', 'years_of_service']) as $period) {
            $had[$period->employee_id][$period->years_of_service] = true;
        }

        $granted = 0;

        foreach ($starts as $employeeId => $start) {
            foreach (PeriodWindow::anniversaries($start, $setting->tracking_since, $today) as $years => $anniversary) {
                // Whoever the roster cannot place still has the law.
                $period = isset($had[$employeeId][$years]) ? null : $this->grant->handle($employeeId, $years, $anniversary, $terms[$employeeId] ?? PolicyTerms::statutory($setting->statutory_work_week));
                $granted += $period === null ? 0 : 1;
            }
        }

        // After granting: the cap of each grant has already taken its part.
        $this->expire->handle();
        $missing = count($active) - count($starts);

        if ($missing > 0) {
            Log::info('leave.start_date_missing', ['people' => $missing]);
        }

        return $granted;
    }

    /**
     * @return list<string>
     */
    private function activeIds(): array
    {
        $centers = array_map(fn (WorkCenterSummary $center): string => $center->id, $this->workCenters->all());

        return array_merge(...array_values($this->roster->activeIdsInMany($centers)));
    }
}
