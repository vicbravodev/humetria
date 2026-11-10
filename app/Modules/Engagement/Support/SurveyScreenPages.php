<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Support\Bank\ItemBank;

/** The questions a person is shown, grouped in the sections the survey screen walks through. */
final class SurveyScreenPages
{
    /**
     * @param  list<BankItem>  $items
     * @return list<array{block: string, title: string, instruction: string, items: list<array{key: string, text: string, scale: string}>}>
     */
    public static function sections(array $items, ?string $managerName): array
    {
        $sections = [];

        foreach (ItemBlock::cases() as $block) {
            $inBlock = array_values(array_filter($items, fn (BankItem $item): bool => $item->block === $block));

            if ($inBlock === []) {
                continue;
            }

            $sections[] = [
                'block' => strtolower($block->name),
                'title' => self::title($block),
                'instruction' => $block === ItemBlock::Manager ? str_replace(':name', $managerName ?? '', ItemBank::MANAGER_INTRO) : '',
                'items' => array_map(fn (BankItem $item): array => ['key' => $item->key, 'text' => $item->text, 'scale' => strtolower($item->scale->name)], $inBlock),
            ];
        }

        return $sections;
    }

    private static function title(ItemBlock $block): string
    {
        return match ($block) {
            ItemBlock::Outcome => 'Su experiencia',
            ItemBlock::Company => 'La empresa',
            ItemBlock::Manager => 'Su jefe directo',
            ItemBlock::Closing => 'Para terminar',
        };
    }
}
