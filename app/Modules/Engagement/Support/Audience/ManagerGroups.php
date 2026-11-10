<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Audience;

/**
 * Splits the invited people into the groups a manager is shown: each person belongs to one group at most, so taking one
 * group from another never leaves fewer than the minimum (the difference attack: «los de la línea de B» minus «los de
 * M»).
 */
final class ManagerGroups
{
    /**
     * Everyone starts in the group of their direct manager. From the deepest manager up, one whose group is under the
     * minimum loses it: their people go to the group of the manager above, or to nobody when there is none.
     *
     * @param  array<string, string|null>  $managerOf  the direct manager of each invitee and of every manager in their chains
     * @param  list<string>  $invitees
     * @return array<string, string|null> for each invitee, the manager that owns their group
     */
    public static function partition(array $managerOf, array $invitees, int $minimum): array
    {
        $owner = [];
        foreach ($invitees as $invitee) {
            $owner[$invitee] = $managerOf[$invitee] ?? null;
        }

        foreach (self::deepestFirst($managerOf, $owner) as $manager) {
            $members = array_keys($owner, $manager, true);

            if ($members !== [] && count($members) < $minimum) {
                foreach ($members as $member) {
                    $owner[$member] = $managerOf[$manager] ?? null;
                }
            }
        }

        return $owner;
    }

    /**
     * Every manager that can own a group: the direct managers of the invitees and everyone above them, the deepest
     * first and by id among equals.
     *
     * @param  array<string, string|null>  $managerOf
     * @param  array<string, string|null>  $owner
     * @return list<string>
     */
    private static function deepestFirst(array $managerOf, array $owner): array
    {
        $depth = [];
        foreach (array_filter($owner) as $direct) {
            for ($manager = $direct; $manager !== null && ! isset($depth[$manager]); $manager = $managerOf[$manager] ?? null) {
                $depth[$manager] = self::depthOf($managerOf, $manager);
            }
        }

        $managers = array_keys($depth);
        usort($managers, fn (string $a, string $b): int => [$depth[$b], $a] <=> [$depth[$a], $b]);

        return $managers;
    }

    /**
     * @param  array<string, string|null>  $managerOf
     */
    private static function depthOf(array $managerOf, string $manager): int
    {
        $depth = 0;
        $seen = [$manager => true];

        for ($above = $managerOf[$manager] ?? null; $above !== null && ! isset($seen[$above]); $above = $managerOf[$above] ?? null) {
            $seen[$above] = true;
            $depth++;
        }

        return $depth;
    }
}
