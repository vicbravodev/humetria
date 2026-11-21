<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Exceptions\PersonWorksHere;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Organization\Contracts\Roster;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RH settles the days of a person who left (criterio C-07): what is left of each tranche of each period leaves the ledger
 * as a termination entry, so every period ends at zero, with who did it and when. Once: a second time, or a termination
 * voided because the person came back, or of a person who works here again, changes nothing. If the balance of today is not
 * the one recorded with the departure, it settles all the same and says so. Days only; what they are worth is the company's payroll.
 */
final readonly class SettleTermination
{
    public function __construct(private Roster $roster, private RecordAudit $audit) {}

    /**
     * @throws AlreadyDecided when it was already settled or voided
     * @throws PersonWorksHere when the person is active again
     * @throws ModelNotFoundException<LeaveTermination>
     */
    public function handle(string $terminationId, int $userId): LeaveTermination
    {
        [$termination, $days, $difference] = DB::transaction(function () use ($terminationId, $userId): array {
            $employeeId = LeaveTermination::query()->findOrFail($terminationId, ['id', 'employee_id'])->employee_id;
            // The lock of the person, then the row: as every change of their days.
            EmployeeLock::of($employeeId);
            $termination = LeaveTermination::query()->lockForUpdate()->findOrFail($terminationId);

            if ($termination->settled_at !== null || $termination->voided_at !== null) {
                throw new AlreadyDecided;
            }

            if ($this->roster->find($employeeId)?->active === true) {
                throw new PersonWorksHere;
            }

            $difference = self::differenceOf($termination);
            $days = 0;

            foreach (Balances::of([$employeeId])[$employeeId] ?? [] as $period) {
                foreach ([[Tranche::Statutory, $period->statutoryLeft], [Tranche::Extra, $period->extraLeft]] as [$tranche, $left]) {
                    if ($left !== 0) {
                        LeaveEntry::query()->create([
                            'tenant_id' => $termination->tenant_id, 'employee_id' => $employeeId, 'leave_entitlement_id' => $period->entitlementId,
                            'kind' => EntryKind::Termination, 'tranche' => $tranche, 'days' => -$left, 'recorded_by' => $userId,
                        ]);
                        $days += $left;
                    }
                }
            }

            $termination->update(['settled_at' => now(), 'settled_by' => $userId]);
            $this->audit->handle('leave.termination_settled', $termination, ['employee_id' => $employeeId, 'days' => $days, ...($difference === 0 ? [] : ['recorded_days' => $termination->pending_days])]);

            return [$termination, $days, $difference];
        });

        Log::info('leave.termination_settled', ['termination_id' => $termination->id, 'employee_id' => $termination->employee_id, 'days' => $days]);

        if ($difference !== 0) {
            Log::warning('leave.termination_settled_with_difference', [
                'termination_id' => $termination->id, 'employee_id' => $termination->employee_id, 'recorded_days' => $termination->pending_days, 'days_today' => $termination->pending_days + $difference,
            ]);
        }

        return $termination;
    }

    /**
     * The days the person has today less the ones recorded with their departure: zero when nothing moved since.
     */
    public static function differenceOf(LeaveTermination $termination): int
    {
        return Balances::available($termination->employee_id) - $termination->pending_days;
    }
}
