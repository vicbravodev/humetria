<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

/**
 * The one place the minimum of respondents per group lives (criterio C-23), for every tool that reports a group. A group
 * under the minimum is not shown, and a list of groups gets complementary suppression: what stays hidden adds up to none
 * or to the minimum at least, so the total minus the groups shown never reveals a small one.
 */
final readonly class GroupMinimum
{
    public function isShown(int $answered): bool
    {
        return $answered >= config()->integer('results.min_group');
    }

    /**
     * The groups that may be shown. Those under the minimum are hidden; while the hidden ones add up to more than none
     * and fewer than the minimum, the smallest group shown (by key on a tie) is hidden too.
     *
     * @param  array<string, int>  $groups  respondents by group key
     */
    public function suppress(array $groups): SuppressionResult
    {
        $visible = array_filter($groups, $this->isShown(...));
        $hidden = array_sum($groups) - array_sum($visible);
        // Smallest first, so the next one to hide is always the first.
        uksort($visible, fn (string $a, string $b): int => [$visible[$a], $a] <=> [$visible[$b], $b]);

        while ($hidden > 0 && ! $this->isShown($hidden) && $visible !== []) {
            $smallest = array_key_first($visible);
            $hidden += $visible[$smallest];
            unset($visible[$smallest]);
        }

        uksort($visible, fn (string $a, string $b): int => [$visible[$b], $a] <=> [$visible[$a], $b]);

        return new SuppressionResult(array_keys($visible), count($groups) - count($visible));
    }
}
