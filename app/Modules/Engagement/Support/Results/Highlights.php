<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Support\Bank\ItemBank;

/** The strengths and opportunities of a group: the best and worst shown questions, in the order of the bank on a tie. */
final class Highlights
{
    /**
     * @param  list<ItemResult>  $items
     * @return array{strengths: list<ItemResult>, opportunities: list<ItemResult>}
     */
    public static function pick(array $items, int $each): array
    {
        $position = array_flip(array_map(fn (BankItem $item): string => $item->key, ItemBank::all()));
        $shown = array_values(array_filter($items, fn (ItemResult $item): bool => $item->favorable !== null));
        $each = min($each, intdiv(count($shown), 2));

        usort($shown, fn (ItemResult $a, ItemResult $b): int => [$b->favorable, $position[$a->key]] <=> [$a->favorable, $position[$b->key]]);
        $strengths = array_slice($shown, 0, $each);

        $rest = array_slice($shown, $each);
        usort($rest, fn (ItemResult $a, ItemResult $b): int => [$a->favorable, $position[$a->key]] <=> [$b->favorable, $position[$b->key]]);

        return ['strengths' => $strengths, 'opportunities' => array_slice($rest, 0, $each)];
    }
}
