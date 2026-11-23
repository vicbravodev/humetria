<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * A departure that is undone (the person comes back before it was settled) takes back what VoidLaterPeriods voided: the
 * periods granted after the day they left were never lost, they only did not apply to someone who had left. Each tranche gets
 * a positive adjustment for what is still voided (what the voids add up to, less what was already restored), so it is safe to
 * run twice and a second departure can void again. Nothing is erased; the entry says why, and who comes back is whoever typed it.
 */
final readonly class RestoreVoidedPeriods
{
    public const string NOTE = 'Baja deshecha: el periodo posterior a la fecha de la baja vuelve a valer.';

    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    /**
     * @return int the days restored
     */
    public function handle(string $employeeId): int
    {
        $restored = 0;
        $periods = 0;

        DB::transaction(function () use ($employeeId, &$restored, &$periods): void {
            $seen = [];
            EmployeeLock::of($employeeId);
            $open = [];

            foreach (LeaveEntry::query()->where('employee_id', $employeeId)->where('kind', EntryKind::Adjustment)->whereNotNull('leave_entitlement_id')
                ->where(fn ($query) => $query->where('note', 'like', VoidLaterPeriods::NOTE.'%')->orWhere('note', self::NOTE))->get() as $entry) {
                $key = $entry->leave_entitlement_id.'|'.$entry->tranche->value;
                $open[$key] = ($open[$key] ?? ['entry' => $entry, 'days' => 0]);
                $open[$key]['days'] += $entry->days;
            }

            $by = Context::get('user_id');

            foreach ($open as ['entry' => $entry, 'days' => $days]) {
                if ($days >= 0) {
                    continue;
                }

                if (! is_int($by)) {
                    throw new LogicException('A voided period is not restored without saying who undid the departure.');
                }

                LeaveEntry::query()->create([
                    'tenant_id' => $this->tenant->id(), 'employee_id' => $employeeId, 'leave_entitlement_id' => $entry->leave_entitlement_id,
                    'kind' => EntryKind::Adjustment, 'tranche' => $entry->tranche, 'days' => -$days, 'recorded_by' => $by, 'note' => self::NOTE,
                ]);
                $restored -= $days;
                $seen[$entry->leave_entitlement_id ?? ''] = true;
            }

            $periods = count($seen);

            if ($periods > 0) {
                $this->audit->handle('leave.entitlements_restored', null, ['employee_id' => $employeeId, 'periods' => $periods, 'days' => $restored]);
                DB::afterCommit(fn () => Log::info('leave.entitlements_restored', ['employee_id' => $employeeId, 'periods' => $periods, 'days' => $restored]));
            }
        });

        return $restored;
    }
}
