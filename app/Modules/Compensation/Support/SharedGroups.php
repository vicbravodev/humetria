<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\CostCut;
use App\Modules\Results\Contracts\SmallGroups;

/**
 * The cuts of a cost for a link anyone with it can open: the groups under the platform's minimum of people are folded into
 * one called «Otros», and while what is folded is still under the minimum the next smallest group goes with it, so that
 * subtracting the subtotals shown from the total never hands over a small group. The sums stay exact, because folding only
 * adds. A cut that would be left with one part is dropped: that part is the total, which is shown anyway.
 */
final class SharedGroups
{
    /** The key of the folded group; no area, category or group has it. */
    public const string OTHERS = '__others__';

    public static function apply(CostBreakdown $cost, SmallGroups $groups): CostBreakdown
    {
        return new CostBreakdown(
            beforeCents: $cost->beforeCents,
            afterCents: $cost->afterCents,
            recurringCents: $cost->recurringCents,
            retroactiveCents: $cost->retroactiveCents,
            people: $cost->people,
            concepts: $cost->concepts,
            months: $cost->months,
            byArea: self::fold($cost->byArea, $groups),
            byGrade: self::fold($cost->byGrade, $groups),
            byGroup: self::fold($cost->byGroup, $groups),
            uncostedGrades: $cost->uncostedGrades,
            uncalculatedLines: $cost->uncalculatedLines,
            staleKeys: $cost->staleKeys,
            gaps: $cost->gaps,
        );
    }

    /**
     * @param  list<CostCut>  $cuts
     * @return list<CostCut>
     */
    private static function fold(array $cuts, SmallGroups $groups): array
    {
        $headcount = [];

        foreach ($cuts as $cut) {
            $headcount[$cut->key ?? ''] = $cut->people;
        }

        $visible = $groups->visibleKeys($headcount);
        $shown = [];
        $others = new CostCut(self::OTHERS, 0, 0, 0);
        $folded = 0;

        foreach ($visible as $key) {
            foreach ($cuts as $cut) {
                if (($cut->key ?? '') === $key) {
                    $shown[] = $cut;
                }
            }
        }

        foreach ($cuts as $cut) {
            if (! in_array($cut->key ?? '', $visible, true)) {
                $others = new CostCut(self::OTHERS, $others->people + $cut->people, $others->beforeCents + $cut->beforeCents, $others->afterCents + $cut->afterCents);
                $folded++;
            }
        }

        $parts = $folded === 0 ? $shown : [...$shown, $others];

        return count($parts) < 2 ? [] : $parts;
    }
}
