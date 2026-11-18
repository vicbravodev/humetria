<?php

declare(strict_types=1);

namespace App\Modules\Results\Contracts;

/**
 * The platform's minimum of people per group (the company's, 5 until it chooses its own, see Tenancy's GroupMinimums), for every tool that reports a group
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

    /** The same rule with a minimum that was fixed when a campaign or a survey launched, instead of the company's now. */
    public function at(int $minimum): self;
}
