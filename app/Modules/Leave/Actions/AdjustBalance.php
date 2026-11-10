<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\EmployeeLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RH's correction of what is left of a tranche of a period: a new entry of the ledger with who made it and why, in favor
 * or against. It never leaves the tranche below zero. The note stays in the ledger: it reaches neither the logs nor the
 * audit trail.
 */
final readonly class AdjustBalance
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @param  int  $days  with its sign: positive in favor of the person
     *
     * @throws ValidationException
     */
    public function handle(string $entitlementId, Tranche $tranche, int $days, string $note, int $userId): LeaveEntry
    {
        $note = trim($note);

        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Explica el motivo del ajuste.']);
        }

        if ($days === 0) {
            throw ValidationException::withMessages(['days' => 'El ajuste no puede ser de 0 días.']);
        }

        $entry = DB::transaction(function () use ($entitlementId, $tranche, $days, $note, $userId): LeaveEntry {
            $period = LeaveEntitlement::query()->findOrFail($entitlementId);
            EmployeeLock::of($period->employee_id);
            $left = 0;

            foreach (Balances::of([$period->employee_id])[$period->employee_id] ?? [] as $balance) {
                if ($balance->entitlementId === $period->id) {
                    $left = $tranche === Tranche::Statutory ? $balance->statutoryLeft : $balance->extraLeft;
                }
            }

            if ($left + $days < 0) {
                throw ValidationException::withMessages(['days' => "No puede quedar bajo cero: a ese tramo le quedan {$left} días."]);
            }

            $entry = LeaveEntry::query()->create([
                'tenant_id' => $period->tenant_id,
                'employee_id' => $period->employee_id,
                'leave_entitlement_id' => $period->id,
                'kind' => EntryKind::Adjustment,
                'tranche' => $tranche,
                'days' => $days,
                'recorded_by' => $userId,
                'note' => $note,
            ]);
            $this->audit->handle('leave.balance_adjusted', $period, ['entry_id' => $entry->id, 'employee_id' => $period->employee_id, 'tranche' => $tranche->value, 'days' => $days]);

            return $entry;
        });

        Log::info('leave.balance_adjusted', ['entry_id' => $entry->id, 'entitlement_id' => $entry->leave_entitlement_id, 'employee_id' => $entry->employee_id, 'tranche' => $tranche->value, 'days' => $days]);

        return $entry;
    }
}
