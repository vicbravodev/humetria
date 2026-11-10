<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Leave\Support\PeriodBalance;
use App\Modules\Leave\Support\PeriodWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Takes off, in the current company, the extra days whose period says they lapsed (criterio C-14): one entry of the ledger
 * for what was left of them, the day after their date. Only the extra tranche: statutory days never lapse, whatever their
 * window (criterio C-11). Running it again changes nothing.
 */
final readonly class ExpireExtraDays
{
    /**
     * @return int how many periods lost extra days
     */
    public function handle(): int
    {
        $lapsed = LeaveEntitlement::query()->where('extra_expires_on', '<', PeriodWindow::today()->toDateString())->where('extra_days', '>', 0)->get(['id', 'tenant_id', 'employee_id']);
        $balances = Balances::of(array_values($lapsed->map(fn (LeaveEntitlement $period): string => $period->employee_id)->unique()->all()));
        $expired = 0;

        foreach ($lapsed as $period) {
            if (self::extraLeft($balances[$period->employee_id] ?? [], $period->id) > 0) {
                $expired += $this->expire($period) ? 1 : 0;
            }
        }

        return $expired;
    }

    private function expire(LeaveEntitlement $period): bool
    {
        $days = DB::transaction(function () use ($period): int {
            // Read again under the lock: a request approved meanwhile may have used them.
            EmployeeLock::of($period->employee_id);
            $days = self::extraLeft(Balances::of([$period->employee_id])[$period->employee_id] ?? [], $period->id);

            if ($days > 0) {
                LeaveEntry::query()->create([
                    'tenant_id' => $period->tenant_id, 'employee_id' => $period->employee_id, 'leave_entitlement_id' => $period->id,
                    'kind' => EntryKind::Expired, 'tranche' => Tranche::Extra, 'days' => -$days,
                ]);
            }

            return $days;
        });

        if ($days > 0) {
            Log::info('leave.extra_days_expired', ['entitlement_id' => $period->id, 'employee_id' => $period->employee_id, 'days' => $days]);
        }

        return $days > 0;
    }

    /**
     * @param  list<PeriodBalance>  $periods
     */
    private static function extraLeft(array $periods, string $entitlementId): int
    {
        foreach ($periods as $balance) {
            if ($balance->entitlementId === $entitlementId) {
                return $balance->extraLeft;
            }
        }

        return 0;
    }
}
