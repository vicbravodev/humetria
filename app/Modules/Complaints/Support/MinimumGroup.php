<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

/**
 * The minimum of people per group (criterio C-47, as C-23 for the results): a count below the minimum is not shown, and
 * neither is a shown one that would give a hidden one away. With the total in view, a single hidden cell is the total minus
 * the shown ones; so, while what is hidden adds up to something under the minimum, the smallest shown cell is hidden too. An
 * empty cell has nobody to protect and shows its zero.
 */
final class MinimumGroup
{
    /**
     * @param  array<string, int>  $counts
     * @return array<string, int|null> null where the number is not shown.
     */
    public static function apply(array $counts, int $minimum): array
    {
        $shown = array_filter($counts, fn (int $count): bool => $count === 0 || $count >= $minimum);
        $hidden = array_sum(array_diff_key($counts, $shown));

        // The smallest shown cell goes next, until what is hidden is nothing or enough.
        while ($hidden > 0 && $hidden < $minimum) {
            $candidates = array_filter($shown, fn (int $count): bool => $count > 0);

            if ($candidates === []) {
                break;
            }

            $smallest = array_keys($candidates, min($candidates), true)[0];
            $hidden += $shown[$smallest];
            unset($shown[$smallest]);
        }

        $cells = [];

        foreach ($counts as $cell => $count) {
            $cells[$cell] = array_key_exists($cell, $shown) ? $count : null;
        }

        return $cells;
    }

    /** The total, or nothing when it is under the minimum. */
    public static function total(int $total, int $minimum): ?int
    {
        return $total === 0 || $total >= $minimum ? $total : null;
    }
}
