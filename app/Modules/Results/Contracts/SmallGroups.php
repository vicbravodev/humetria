<?php

declare(strict_types=1);

namespace App\Modules\Results\Contracts;

/**
 * The platform's minimum of people per group (`results.min_group`, 5 by default), for every tool that reports a group
 * to someone who must not be able to tell who is in it. A group under the minimum is not shown, and what stays hidden adds
 * up to none or to the minimum at least, so the total minus the groups shown never reveals a small one.
 */
interface SmallGroups
{
    /**
     * The groups that may be shown, the largest first (by key on a tie).
     *
     * @param  array<string, int>  $headcountByGroup  people by group key
     * @return list<string>
     */
    public function visibleKeys(array $headcountByGroup): array;
}
