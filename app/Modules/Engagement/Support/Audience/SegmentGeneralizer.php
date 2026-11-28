<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Audience;

use App\Modules\Engagement\Enums\CutAttribute;
use Closure;

/**
 * Coarsens the segments of the invited people until none is small enough to point at someone. Tenure goes first, then
 * area, work center and manager group: the finest and the least useful to act on go before the ones a plan is made on.
 */
final class SegmentGeneralizer
{
    /** @var list<CutAttribute> */
    private const array ORDER = [CutAttribute::Tenure, CutAttribute::Area, CutAttribute::WorkCenter, CutAttribute::Manager]; // @pest-mutate-ignore: RemoveArrayItem

    /**
     * Per field in order, counts how many rows share each full combination and drops that field from the rows whose
     * combination is under the minimum, before counting again for the next field. Counts only grow as fields drop, so
     * one pass leaves every combination at the minimum or fully generalized (the whole company).
     *
     * @param  array<string, Segment>  $segments  by row id
     * @param  Closure(int): bool  $met  whether a group of that many people may be shown
     * @return array<string, Segment> the same ids
     */
    public static function generalize(array $segments, Closure $met): array
    {
        foreach (self::ORDER as $field) {
            $counts = array_count_values(array_map(fn (Segment $segment): string => $segment->key(), $segments));

            foreach ($segments as $id => $segment) {
                if ($segment->value($field) !== null && ! $met($counts[$segment->key()])) {
                    $segments[$id] = $segment->without($field);
                }
            }
        }

        return self::closeCompanyCell($segments, $met);
    }

    /**
     * The company-only cell takes part in every subtraction from the company total, so it must be empty or at the
     * minimum: while it is under, the smallest remaining cell (by key on a tie) joins it.
     *
     * @param  array<string, Segment>  $segments
     * @param  Closure(int): bool  $met
     * @return array<string, Segment>
     */
    private static function closeCompanyCell(array $segments, Closure $met): array
    {
        $none = new Segment(null, null, null, null);

        while (true) {
            $counts = array_count_values(array_map(fn (Segment $segment): string => $segment->key(), $segments));
            $rest = $counts[$none->key()] ?? 0;
            unset($counts[$none->key()]);

            if ($rest === 0 || $met($rest) || $counts === []) {
                return $segments;
            }

            $keys = array_keys($counts);
            usort($keys, fn (string $a, string $b): int => [$counts[$a], $a] <=> [$counts[$b], $b]);

            foreach ($segments as $id => $segment) {
                if ($segment->key() === $keys[0]) {
                    $segments[$id] = $none;
                }
            }
        }
    }
}
