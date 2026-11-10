<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\ItemScale;
use App\Modules\Engagement\Support\Thresholds;
use InvalidArgumentException;

/** Counts the answers to one agreement question. The minimum is applied to the answers to the item, not to the rows. */
final class ItemTally
{
    /** @param  list<ResponseRow>  $rows */
    public static function of(BankItem $item, array $rows, Thresholds $thresholds): ItemResult
    {
        if ($item->scale !== ItemScale::Agreement) {
            throw new InvalidArgumentException("{$item->key} is not an agreement question.");
        }

        $counts = array_fill(1, 5, 0);
        foreach ($rows as $row) {
            $value = $row->answers[$item->key] ?? null;
            if ($value !== null) {
                $counts[$value]++;
            }
        }

        $answered = array_sum($counts);
        if (! $thresholds->met($answered)) {
            return new ItemResult($item->key, false, null, null, null, null, null);
        }

        return new ItemResult(
            $item->key,
            true,
            $answered,
            self::percent($counts[4] + $counts[5], $answered),
            self::percent($counts[3], $answered),
            self::percent($counts[1] + $counts[2], $answered),
            array_values(array_map(fn (int $count): int => self::percent($count, $answered), $counts)),
        );
    }

    private static function percent(int $part, int $whole): int
    {
        return intdiv(200 * $part + $whole, 2 * $whole);
    }
}
