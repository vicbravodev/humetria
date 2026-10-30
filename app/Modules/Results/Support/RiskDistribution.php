<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\GroupScore;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Instruments\Data\RiskScore;
use App\Modules\Responses\Data\AnonymousRecord;

/**
 * The one place every result of a Guía II or III goes through. A result is how many questionnaires fall in each risk
 * level, never an average or a sum (criterio C-14), and no group is shown with fewer questionnaires than the minimum
 * (criterio C-23), applied after every filter. Areas get complementary suppression: what stays hidden adds up to none
 * or to the minimum at least, so the center minus the areas shown never reveals a small one.
 *
 * Levels always come in the order of RiskLevel: Nulo, Bajo, Medio, Alto, Muy alto.
 */
final readonly class RiskDistribution
{
    public function __construct(private Instruments $instruments) {}

    /**
     * The final score, categories and domains of the questionnaires, or of one area when it is an area shown.
     *
     * @param  list<AnonymousRecord>  $records
     * @return array{answered: int, visible: bool, final: list<int>, categories: list<array{key: string, label: string, levels: list<int>}>, domains: list<array{key: string, label: string, levels: list<int>}>}
     */
    public function of(string $instrument, array $records, ?string $areaId = null): array
    {
        if ($areaId !== null && in_array($areaId, $this->visibleAreas($records), true)) {
            $records = array_values(array_filter($records, fn (AnonymousRecord $record): bool => $record->areaId === $areaId));
        }

        $answered = count($records);

        if (! $this->isShown($answered)) {
            return ['answered' => $answered, 'visible' => false, 'final' => [], 'categories' => [], 'domains' => []];
        }

        $scores = $this->scores($instrument, $records);

        return [
            'answered' => $answered,
            'visible' => true,
            'final' => self::count(array_map(fn (RiskScore $score): RiskLevel => $score->level, $scores)),
            'categories' => self::groups(array_map(fn (RiskScore $score): array => $score->categories, $scores)),
            'domains' => self::groups(array_map(fn (RiskScore $score): array => $score->domains, $scores)),
        ];
    }

    /**
     * The final score of each area shown, the largest first, and how many areas stay hidden.
     *
     * @param  list<AnonymousRecord>  $records
     * @return array{visible: list<array{areaId: string, answered: int, final: list<int>}>, hidden: int}
     */
    public function areas(string $instrument, array $records): array
    {
        $visible = $this->visibleAreas($records);
        $sizes = self::sizes($records);
        $areas = [];

        foreach ($visible as $areaId) {
            $inArea = array_values(array_filter($records, fn (AnonymousRecord $record): bool => $record->areaId === $areaId));
            $areas[] = [
                'areaId' => $areaId,
                'answered' => count($inArea),
                'final' => self::count(array_map(fn (RiskScore $score): RiskLevel => $score->level, $this->scores($instrument, $inArea))),
            ];
        }

        return ['visible' => $areas, 'hidden' => count($sizes) - count($visible)];
    }

    /**
     * How each question was answered, by section, with the count of each option in the order the questionnaire offers
     * them. A question answered by fewer than the minimum (the blocks behind a gate are answered by some) shows none.
     *
     * @param  list<AnonymousRecord>  $records
     * @return list<array{title: string|null, instruction: string, items: list<array{number: int, text: string, answered: int, counts: list<int>|null}>}>
     */
    public function items(string $instrument, array $records): array
    {
        $definition = $this->instruments->definition($instrument);
        $options = array_column($definition->options, 'value');
        $sections = [];

        foreach ($definition->sections as $section) {
            $items = [];

            foreach ($section->items as $item) {
                $given = array_values(array_filter(array_map(fn (AnonymousRecord $record): ?string => $record->answers[$item->number] ?? null, $records)));
                $counts = array_map(fn (string $option): int => count(array_keys($given, $option, true)), $options);
                $answered = count($given);
                $items[] = ['number' => $item->number, 'text' => $item->text, 'answered' => $answered, 'counts' => $this->isShown($answered) ? $counts : null];
            }

            $sections[] = ['title' => $section->title, 'instruction' => $section->instruction, 'items' => $items];
        }

        return $sections;
    }

    /**
     * The areas that may be shown. Those under the minimum are hidden; while the hidden ones add up to more than none
     * and fewer than the minimum, the smallest area shown (by id on a tie) is hidden too.
     *
     * @param  list<AnonymousRecord>  $records
     * @return list<string> largest first, by id on a tie
     */
    private function visibleAreas(array $records): array
    {
        $sizes = self::sizes($records);
        $visible = array_filter($sizes, $this->isShown(...));
        $hidden = count($records) - array_sum($visible);
        // Smallest first, so the next one to hide is always the first.
        uksort($visible, fn (string $a, string $b): int => [$visible[$a], $a] <=> [$visible[$b], $b]);

        while ($hidden > 0 && ! $this->isShown($hidden) && $visible !== []) {
            $smallest = array_key_first($visible);
            $hidden += $visible[$smallest];
            unset($visible[$smallest]);
        }

        uksort($visible, fn (string $a, string $b): int => [$visible[$b], $a] <=> [$visible[$a], $b]);

        return array_keys($visible);
    }

    private function isShown(int $answered): bool
    {
        return $answered >= config()->integer('results.min_group');
    }

    /**
     * @param  list<AnonymousRecord>  $records
     * @return array<string, int> questionnaires by area
     */
    private static function sizes(array $records): array
    {
        $sizes = [];

        foreach ($records as $record) {
            $sizes[$record->areaId] = ($sizes[$record->areaId] ?? 0) + 1;
        }

        return $sizes;
    }

    /**
     * Each questionnaire scored on its own, in the version of the norm it was given in.
     *
     * @param  list<AnonymousRecord>  $records
     * @return list<RiskScore>
     */
    private function scores(string $instrument, array $records): array
    {
        return array_map(fn (AnonymousRecord $record): RiskScore => $this->instruments->scoreRisk($instrument, $record->answers), $records);
    }

    /**
     * @param  list<list<GroupScore>>  $groupsOfEach  the categories (or domains) of each questionnaire, in the same order
     * @return list<array{key: string, label: string, levels: list<int>}>
     */
    private static function groups(array $groupsOfEach): array
    {
        $groups = [];

        foreach ($groupsOfEach[0] ?? [] as $index => $group) {
            $groups[] = [
                'key' => $group->key,
                'label' => $group->label,
                'levels' => self::count(array_map(fn (array $groupsOfOne): RiskLevel => $groupsOfOne[$index]->level, $groupsOfEach)),
            ];
        }

        return $groups;
    }

    /**
     * @param  list<RiskLevel>  $levels
     * @return list<int>
     */
    private static function count(array $levels): array
    {
        return array_map(fn (RiskLevel $level): int => count(array_keys($levels, $level, true)), RiskLevel::cases());
    }
}
