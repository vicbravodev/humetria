<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * A period whose anniversary falls after the day a person left was never earned (criterio C-07): the nightly grant may
 * have given it before HR typed the departure. What is left of it is voided with an adjustment of the ledger that says why;
 * nothing is erased. The anniversary of the same day counts: the person completed that year. The entry carries who
 * typed the departure (the ledger asks who made every adjustment): a departure always has someone behind it.
 */
final readonly class VoidLaterPeriods
{
    /** What every voiding entry's note starts with: RestoreVoidedPeriods finds them by it. */
    public const string NOTE = 'Periodo posterior a la fecha de la baja';

    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    /**
     * @return int the days voided
     */
    public function handle(string $employeeId, CarbonImmutable $effectiveOn): int
    {
        $day = $effectiveOn->toDateString();
        $voided = 0;
        $periods = 0;

        DB::transaction(function () use ($employeeId, $day, $effectiveOn, &$voided, &$periods): void {
            EmployeeLock::of($employeeId);

            foreach (Balances::of([$employeeId])[$employeeId] ?? [] as $period) {
                if ($period->anniversaryOn->toDateString() <= $day) {
                    continue;
                }

                $periods++;
                $by = Context::get('user_id');

                if (! is_int($by)) {
                    throw new LogicException('A period is not voided without saying who typed the departure.');
                }

                foreach ([[Tranche::Statutory, $period->statutoryLeft], [Tranche::Extra, $period->extraLeft]] as [$tranche, $left]) {
                    if ($left !== 0) {
                        LeaveEntry::query()->create([
                            'tenant_id' => $this->tenant->id(), 'employee_id' => $employeeId, 'leave_entitlement_id' => $period->entitlementId,
                            'kind' => EntryKind::Adjustment, 'tranche' => $tranche, 'days' => -$left, 'recorded_by' => $by,
                            'note' => self::NOTE.' ('.$effectiveOn->format('d/m/Y').'): no llegó a cumplirse.',
                        ]);
                        $voided += $left;
                    }
                }
            }

            if ($periods > 0) {
                $this->audit->handle('leave.entitlements_voided', null, ['employee_id' => $employeeId, 'periods' => $periods, 'days' => $voided]);
            }
        });

        if ($periods > 0) {
            // Said once it committed: this runs inside the transaction of the departure, which may still roll back.
            DB::afterCommit(fn () => Log::info('leave.entitlements_voided', ['employee_id' => $employeeId, 'periods' => $periods, 'days' => $voided]));
        }

        return $voided;
    }
}
