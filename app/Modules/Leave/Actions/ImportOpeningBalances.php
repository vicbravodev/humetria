<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Leave\Support\PeriodWindow;
use App\Modules\Leave\Support\PolicyResolver;
use App\Modules\Leave\Support\PolicyTerms;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Loads the opening balances PlanOpeningBalances found ready (criterio C-21): each period from before the company started,
 * with the days its year gave by the table of its anniversary (criterio C-02) and the terms of the person's policy, and what
 * was still pending of it as its opening entries, the statutory tranche first. All of them or none. A period that already
 * exists is left as it is. No certificate comes out of these periods (criterio C-10).
 */
final readonly class ImportOpeningBalances
{
    public function __construct(private CurrentTenant $tenant, private PolicyResolver $policies, private RecordAudit $audit) {}

    /**
     * @param  list<array{line: int, employeeId: string, years: int, anniversary: string, days: int}>  $ready
     * @return int how many periods were created
     */
    public function handle(array $ready, int $userId): int
    {
        if ($ready === []) {
            return 0;
        }

        [$periods, $days] = DB::transaction(function () use ($ready, $userId): array {
            $terms = $this->policies->for(array_values(array_unique(array_column($ready, 'employeeId'))));
            $law = PolicyTerms::statutory(LeaveSetting::query()->sole()->statutory_work_week);
            $periods = 0;
            $days = 0;

            foreach ($ready as $row) {
                if ($this->load($row, $terms[$row['employeeId']] ?? $law, $userId)) {
                    $periods++;
                    $days += $row['days'];
                }
            }

            if ($periods > 0) {
                $this->audit->handle('leave.opening_balances_imported', metadata: ['periods' => $periods, 'days' => $days]);
            }

            return [$periods, $days];
        });

        if ($periods > 0) {
            Log::info('leave.opening_balances_imported', ['periods' => $periods, 'days' => $days]);
        }

        return $periods;
    }

    /**
     * @param  array{employeeId: string, years: int, anniversary: string, days: int}  $row
     */
    private function load(array $row, PolicyTerms $terms, int $userId): bool
    {
        EmployeeLock::of($row['employeeId']);

        if (LeaveEntitlement::query()->where('employee_id', $row['employeeId'])->where('years_of_service', $row['years'])->exists()) {
            return false;
        }

        $anniversary = CarbonImmutable::parse($row['anniversary']);
        $gave = $terms->daysFor($row['years'], $anniversary);
        $window = PeriodWindow::of($anniversary);
        $period = LeaveEntitlement::query()->create([
            'tenant_id' => $this->tenant->id(),
            'employee_id' => $row['employeeId'],
            'years_of_service' => $row['years'],
            'anniversary_on' => $row['anniversary'],
            'leave_policy_id' => $terms->policyId,
            'policy_name' => $terms->name,
            'premium_percent' => $terms->premiumPercent,
            'statutory_days' => $gave['statutory'],
            'extra_days' => $gave['extra'],
            'granted_on' => PeriodWindow::today()->toDateString(),
            'enjoy_by' => $window->enjoyBy->toDateString(),
            'prescribes_on' => $window->prescribesOn->toDateString(),
            'extra_expires_on' => $terms->extraExpiresMonths === null ? null : $anniversary->addMonthsNoOverflow($terms->extraExpiresMonths)->toDateString(),
            'source' => LeaveEntitlement::OPENING,
        ]);
        $statutory = min($row['days'], $gave['statutory']);

        foreach ([[Tranche::Statutory, $statutory], [Tranche::Extra, $row['days'] - $statutory]] as [$tranche, $pending]) {
            if ($pending > 0) {
                LeaveEntry::query()->create([
                    'tenant_id' => $period->tenant_id, 'employee_id' => $period->employee_id, 'leave_entitlement_id' => $period->id,
                    'kind' => EntryKind::Opening, 'tranche' => $tranche, 'days' => $pending, 'recorded_by' => $userId,
                ]);
            }
        }

        return true;
    }
}
