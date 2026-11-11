<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\CutAttribute;
use App\Modules\Engagement\Support\Audience\SegmentGeneralizer;
use App\Modules\Engagement\Support\Thresholds;

/**
 * Cuts the responses by one attribute. First the responses are generalized, as the invitations were at launch but
 * counting answers: a cell of one to four responses only counts at company level, so two groups of different attributes
 * never differ by one to four people. Then the groups are grouped and the platform's minimum, with its complementary
 * suppression, decides which are shown. The people with no value for the attribute and the ones generalized away make up
 * a rest that takes part in the suppression and is never returned.
 */
final class CutReport
{
    private const string REST = '__rest';

    /** @param  list<ResponseRow>  $rows */
    public static function of(CutAttribute $by, array $rows, Thresholds $thresholds): CutGroups
    {
        $segments = [];
        foreach ($rows as $index => $row) {
            $segments["r{$index}"] = $row->segment;
        }

        $groups = [];
        foreach (SegmentGeneralizer::generalize($segments, $thresholds->met(...)) as $id => $segment) {
            $groups[$segment->value($by) ?? self::REST][] = new ResponseRow($segment, $rows[(int) substr($id, 1)]->answers);
        }

        $shown = [];
        foreach ($thresholds->visible(array_map('count', $groups)) as $key) {
            if ($key !== self::REST) {
                $shown[$key] = $groups[$key];
            }
        }

        return new CutGroups($shown, count($shown) !== count($groups));
    }
}
