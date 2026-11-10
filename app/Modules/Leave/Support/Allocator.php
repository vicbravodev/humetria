<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\Tranche;
use InvalidArgumentException;

/**
 * The order in which vacation days are used up (criterio C-16): the oldest period first and, within it, the statutory
 * days before the extra ones. A period that can already prescribe is not skipped: it is the first to go.
 */
final class Allocator
{
    /**
     * @param  list<PeriodBalance>  $periods  in any order
     */
    public static function allocate(array $periods, int $days): Allocation
    {
        if ($days < 1) {
            throw new InvalidArgumentException('A request takes at least one day.');
        }

        usort($periods, fn (PeriodBalance $left, PeriodBalance $right): int => $left->anniversaryOn <=> $right->anniversaryOn);

        $lines = [];

        foreach ($periods as $period) {
            foreach ([[Tranche::Statutory, $period->statutoryLeft], [Tranche::Extra, $period->extraLeft]] as [$tranche, $left]) {
                $take = min($days, $left);

                if ($take > 0) {
                    $lines[] = new AllocationLine($period->entitlementId, $tranche, $take);
                    $days -= $take;
                }
            }
        }

        return new Allocation($lines, $days);
    }
}
