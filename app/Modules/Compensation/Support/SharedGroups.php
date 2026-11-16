<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\CostCell;
use App\Modules\Compensation\Data\CostCut;
use App\Modules\Results\Contracts\SmallGroups;

/**
 * The cuts of a cost for a link anyone with it can open: the groups under the platform's minimum of people are folded into
 * one called «Otros», and while what is folded is still under the minimum the next smallest group goes with it, so that
 * subtracting the subtotals shown from the total never hands over a small group. The sums stay exact, because folding only
 * adds. The folding is done on the cross of the three cuts, so that no difference between groups of different cuts (an
 * area minus a category) hands one over either: the people of the same area, category and group are a cell, every cell under
 * the minimum goes to «Otros» in all three cuts, and the smallest cell follows while «Otros» holds fewer than the minimum.
 * A cut that would be left with one part is dropped: that part is the total, which is shown anyway.
 */
final class SharedGroups
{
    /** The key of the folded group; no area, category or group has it. */
    public const string OTHERS = '__others__';

    public static function apply(CostBreakdown $cost, SmallGroups $groups): CostBreakdown
    {
        $others = self::foldedCells($cost->cells, $groups);

        return new CostBreakdown(
            beforeCents: $cost->beforeCents,
            afterCents: $cost->afterCents,
            recurringCents: $cost->recurringCents,
            retroactiveCents: $cost->retroactiveCents,
            people: $cost->people,
            concepts: $cost->concepts,
            months: $cost->months,
            byArea: $others === null ? self::fold($cost->byArea, $groups) : self::sum($cost->byArea, $others, fn (CostCell $cell): ?string => $cell->areaId),
            byGrade: $others === null ? self::fold($cost->byGrade, $groups) : self::sum($cost->byGrade, $others, fn (CostCell $cell): string => $cell->jobGradeId),
            byGroup: $others === null ? self::fold($cost->byGroup, $groups) : self::sum($cost->byGroup, $others, fn (CostCell $cell): string => $cell->group),
            uncostedGrades: $cost->uncostedGrades,
            uncalculatedLines: $cost->uncalculatedLines,
            staleKeys: $cost->staleKeys,
            gaps: $cost->gaps,
            cells: $cost->cells,
        );
    }

    /**
     * The cells split into the ones that stay and the ones folded; null when the breakdown has no cells to cross.
     *
     * @param  list<CostCell>  $cells
     * @return array{shown: list<CostCell>, folded: list<CostCell>}|null
     */
    private static function foldedCells(array $cells, SmallGroups $groups): ?array
    {
        if ($cells === []) {
            return null;
        }

        $headcount = [];

        foreach ($cells as $index => $cell) {
            $headcount["c{$index}"] = $cell->people;
        }

        $visible = $groups->visibleKeys($headcount);
        $shown = array_values(array_filter($cells, fn (int $index): bool => in_array("c{$index}", $visible, true), ARRAY_FILTER_USE_KEY));
        $folded = array_values(array_filter($cells, fn (int $index): bool => ! in_array("c{$index}", $visible, true), ARRAY_FILTER_USE_KEY));

        // «Otros» of 1 to minimum - 1 people would be handed over by a difference: the smallest cell that is left goes with it.
        while ($shown !== [] && $folded !== [] && $groups->visibleKeys(['others' => array_sum(array_map(fn (CostCell $cell): int => $cell->people, $folded))]) === []) {
            usort($shown, fn (CostCell $a, CostCell $b): int => $b->people <=> $a->people);
            $folded[] = array_pop($shown);
        }

        return ['shown' => $shown, 'folded' => $folded];
    }

    /**
     * One cut added up from the cells: each key shown, then «Otros» with all that was folded, whatever its key in this cut.
     *
     * @param  list<CostCut>  $whole  the cut with nothing folded: with one part only it is the total, which is shown anyway
     * @param  array{shown: list<CostCell>, folded: list<CostCell>}  $cells
     * @param  callable(CostCell): ?string  $keyOf
     * @return list<CostCut>
     */
    private static function sum(array $whole, array $cells, callable $keyOf): array
    {
        if (count($whole) < 2) {
            return [];
        }

        $parts = [];

        foreach ($cells['shown'] as $cell) {
            $key = $keyOf($cell);
            $previous = $parts[$key ?? ''] ?? new CostCut($key, 0, 0, 0);
            $parts[$key ?? ''] = new CostCut($key, $previous->people + $cell->people, $previous->beforeCents + $cell->beforeCents, $previous->afterCents + $cell->afterCents);
        }

        $parts = array_values($parts);

        if ($cells['folded'] !== []) {
            $parts[] = new CostCut(
                self::OTHERS,
                array_sum(array_map(fn (CostCell $cell): int => $cell->people, $cells['folded'])),
                array_sum(array_map(fn (CostCell $cell): int => $cell->beforeCents, $cells['folded'])),
                array_sum(array_map(fn (CostCell $cell): int => $cell->afterCents, $cells['folded'])),
            );
        }

        return count($parts) < 2 ? [] : $parts;
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
