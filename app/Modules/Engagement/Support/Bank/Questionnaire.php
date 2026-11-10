<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Bank;

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\ItemBlock;
use InvalidArgumentException;

/** Which questions of the bank a survey asks, and which of them a given person is shown. */
final class Questionnaire
{
    /**
     * The refs (`key@wording`) of the climate survey, in the order of the bank. The loop question asks about the actions
     * of the previous wave, so it only exists from the second wave on.
     *
     * @return list<string>
     */
    public static function climate(bool $hasPreviousWave): array
    {
        $items = array_filter(ItemBank::all(), fn (BankItem $item): bool => $hasPreviousWave || $item->key !== ItemBank::LOOP);

        return array_values(array_map(fn (BankItem $item): string => $item->ref(), $items));
    }

    /**
     * The questions of a survey that a person is shown: someone without a manager does not see the manager block.
     *
     * @param  list<string>  $refs
     * @return list<BankItem>
     *
     * @throws InvalidArgumentException when a ref is not in the bank with that very wording
     */
    public static function presentedTo(array $refs, bool $hasManager): array
    {
        $items = array_map(self::resolve(...), $refs);

        return array_values(array_filter($items, fn (BankItem $item): bool => $hasManager || $item->block !== ItemBlock::Manager));
    }

    private static function resolve(string $ref): BankItem
    {
        $item = ItemBank::find(explode('@', $ref)[0]);

        if ($item->ref() !== $ref) {
            throw new InvalidArgumentException("The wording [{$ref}] is not the one of the bank.");
        }

        return $item;
    }
}
