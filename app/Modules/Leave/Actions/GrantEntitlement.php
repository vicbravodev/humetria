<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Leave\Support\PeriodWindow;
use App\Modules\Leave\Support\PolicyTerms;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Grants the period of one year of service, whole, on its anniversary (criterio C-03): the period with the name and the
 * terms of the policy of that day (criterio C-15) and its window (criterios C-09 and C-11), and the entries that put its
 * days in the ledger. Never a second period for the same year of service (criterio C-21).
 */
final readonly class GrantEntitlement
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @return LeaveEntitlement|null null when that year of service already has its period
     */
    public function handle(string $employeeId, int $years, CarbonImmutable $anniversary, PolicyTerms $terms): ?LeaveEntitlement
    {
        $period = DB::transaction(function () use ($employeeId, $years, $anniversary, $terms): ?LeaveEntitlement {
            EmployeeLock::of($employeeId);

            if (LeaveEntitlement::query()->where('employee_id', $employeeId)->where('years_of_service', $years)->exists()) {
                return null;
            }

            $days = $terms->daysFor($years, $anniversary);
            $window = PeriodWindow::of($anniversary);
            $period = LeaveEntitlement::query()->create([
                'tenant_id' => $this->tenant->id(),
                'employee_id' => $employeeId,
                'years_of_service' => $years,
                'anniversary_on' => $anniversary->toDateString(),
                'leave_policy_id' => $terms->policyId,
                'policy_name' => $terms->name,
                'premium_percent' => $terms->premiumPercent,
                'statutory_days' => $days['statutory'],
                'extra_days' => $days['extra'],
                'granted_on' => PeriodWindow::today()->toDateString(),
                'enjoy_by' => $window->enjoyBy->toDateString(),
                'prescribes_on' => $window->prescribesOn->toDateString(),
                'extra_expires_on' => $terms->extraExpiresMonths === null ? null : $anniversary->addMonthsNoOverflow($terms->extraExpiresMonths)->toDateString(),
                'source' => LeaveEntitlement::ANNIVERSARY,
            ]);

            foreach ([[Tranche::Statutory, $days['statutory']], [Tranche::Extra, $days['extra']]] as [$tranche, $granted]) {
                if ($granted > 0) {
                    LeaveEntry::query()->create([
                        'tenant_id' => $period->tenant_id, 'employee_id' => $employeeId, 'leave_entitlement_id' => $period->id,
                        'kind' => EntryKind::Granted, 'tranche' => $tranche, 'days' => $granted,
                    ]);
                }
            }

            return $period;
        });

        if ($period !== null) {
            Log::info('leave.entitlement_granted', [
                'entitlement_id' => $period->id, 'employee_id' => $employeeId, 'years_of_service' => $years,
                'statutory_days' => $period->statutory_days, 'extra_days' => $period->extra_days,
            ]);
        }

        return $period;
    }
}
