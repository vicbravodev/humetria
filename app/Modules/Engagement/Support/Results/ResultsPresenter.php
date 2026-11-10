<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\CutAttribute;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Enums\ItemScale;
use App\Modules\Engagement\Enums\Priority;
use App\Modules\Engagement\Enums\TenureBand;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Thresholds;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * The props of the results page: the company and, when asked, one cut. Only aggregates reach the page, and a group the
 * minimums hide is not there at all, not even as a number.
 */
final readonly class ResultsPresenter
{
    public function __construct(
        private Thresholds $thresholds,
        private WorkCenters $workCenters,
        private Areas $areas,
        private Roster $roster,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(SurveyResults $results, ?CutAttribute $cut): array
    {
        $survey = $results->survey();
        $base = [
            'survey' => ['id' => $survey->id, 'name' => $survey->name, 'status' => $survey->status->value],
            'available' => $results->available(),
            'cut' => $cut?->value,
            'cuts' => array_map(fn (CutAttribute $attribute): array => ['value' => $attribute->value, 'label' => self::cutLabel($attribute)], CutAttribute::cases()),
        ];

        if (! $results->available()) {
            return [...$base, 'participation' => null, 'index' => null, 'indexDelta' => null, 'stay' => null, 'recommendation' => null, 'drivers' => [], 'highlights' => null, 'priorities' => [], 'comparison' => null, 'groups' => null, 'hasHidden' => false];
        }

        $company = $results->company();
        $previous = $results->previous();
        $before = $previous?->company();
        $groups = $cut === null ? null : $results->cut($cut);
        $invited = $survey->invited ?? 0;

        return [
            ...$base,
            'participation' => ['invited' => $invited, 'responses' => $company->responses, 'percent' => $invited > 0 ? min(100, intdiv(200 * $company->responses + $invited, 2 * $invited)) : 0],
            'index' => $company->index,
            'indexDelta' => $previous === null || $before === null ? null : $this->delta($this->indexScore($company, $results), $this->indexScore($before, $previous), $results->sameWording(ItemBank::INDEX[0], $previous)),
            'stay' => $this->stay($company),
            'recommendation' => $this->recommendation($company->recommendation),
            'drivers' => $this->drivers($company, $results, $previous, $before),
            'highlights' => $this->highlights($company->highlights),
            'priorities' => $this->priorities($survey->priorities ?? []),
            'comparison' => $previous === null ? null : ['name' => $previous->survey()->name],
            'groups' => $groups === null ? null : $this->groups($cut, $groups['groups'], $company),
            'hasHidden' => $groups['hasHidden'] ?? false,
        ];
    }

    /**
     * The props of the page by question: every agreement question the survey asked about the company, by theme, with
     * its shares, how many answered and how it moved since the previous survey.
     *
     * @return array<string, mixed>
     */
    public function itemsProps(SurveyResults $results): array
    {
        $survey = $results->survey();
        $base = ['survey' => ['id' => $survey->id, 'name' => $survey->name, 'status' => $survey->status->value], 'available' => $results->available()];

        if (! $results->available()) {
            return [...$base, 'sections' => []];
        }

        $byKey = $results->company()->byKey;
        $previous = $results->previous();
        $before = $previous?->company()->byKey ?? [];
        $sections = [['key' => 'outcome', 'label' => 'Compromiso', 'score' => null, 'note' => null, 'keys' => array_map(fn (BankItem $item): string => $item->key, array_filter(ItemBank::all(), fn (BankItem $item): bool => $item->block === ItemBlock::Outcome && $item->scale === ItemScale::Agreement))]];
        foreach ($results->company()->drivers as $driver) {
            $sections[] = ['key' => $driver->driver->value, 'label' => $driver->driver->label(), 'score' => $driver->favorable, 'note' => $driver->descriptive ? 'Se reporta pregunta por pregunta.' : null, 'keys' => array_map(fn (ItemResult $item): string => $item->key, $driver->items)];
        }
        $sections[] = ['key' => 'loop', 'label' => 'Cerrar el ciclo', 'score' => null, 'note' => null, 'keys' => [ItemBank::LOOP]];

        $out = [];
        foreach ($sections as $section) {
            $items = [];
            foreach ($section['keys'] as $key) {
                if (isset($byKey[$key])) {
                    $items[] = $this->detail($byKey[$key], $before[$key] ?? null, $previous !== null && $results->sameWording($key, $previous));
                }
            }
            if ($items !== []) {
                $out[] = ['key' => $section['key'], 'label' => $section['label'], 'score' => $section['score'], 'note' => $section['note'], 'items' => $items];
            }
        }

        return [...$base, 'sections' => $out];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(ItemResult $item, ?ItemResult $before, bool $same): array
    {
        return [
            'key' => $item->key,
            'text' => ItemBank::find($item->key)->text,
            'shown' => $item->shown,
            'answered' => $item->answered,
            'favorable' => $item->favorable,
            'neutral' => $item->neutral,
            'unfavorable' => $item->unfavorable,
            'distribution' => $item->distribution,
            'delta' => $this->delta($item, $before, $same),
        ];
    }

    public static function cutLabel(CutAttribute $cut): string
    {
        return match ($cut) {
            CutAttribute::WorkCenter => 'Centro de trabajo',
            CutAttribute::Area => 'Área',
            CutAttribute::Tenure => 'Antigüedad',
            CutAttribute::Manager => 'Jefe',
        };
    }

    /**
     * @return array{shown: bool, favorable: int|null}
     */
    private function stay(GroupSummary $summary): array
    {
        $result = $summary->byKey[ItemBank::STAY] ?? null;

        return ['shown' => $result->shown ?? false, 'favorable' => $result?->favorable];
    }

    /**
     * @return array{shown: bool, index: int|null, bands: array{detractors: int, passives: int, promoters: int}|null}
     */
    private function recommendation(RecommendationResult $result): array
    {
        return ['shown' => $result->shown, 'index' => $result->index, 'bands' => $result->bands];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drivers(GroupSummary $company, SurveyResults $results, ?SurveyResults $previous, ?GroupSummary $before): array
    {
        $beforeByDriver = [];
        foreach ($before->drivers ?? [] as $driver) {
            $beforeByDriver[$driver->driver->value] = $driver;
        }

        return array_map(function (DriverResult $driver) use ($results, $previous, $beforeByDriver): array {
            $earlier = $beforeByDriver[$driver->driver->value] ?? null;
            $same = $previous !== null && array_reduce($driver->items, fn (bool $all, ItemResult $item): bool => $all && $results->sameWording($item->key, $previous), true);

            return [
                'key' => $driver->driver->value,
                'label' => $driver->driver->label(),
                'descriptive' => $driver->descriptive,
                'favorable' => $driver->favorable,
                'delta' => $earlier === null ? null : $this->delta($this->score($driver), $this->score($earlier), $same),
                'items' => array_map($this->item(...), $driver->items),
            ];
        }, $company->drivers);
    }

    /**
     * @param  array{strengths: list<ItemResult>, opportunities: list<ItemResult>}  $highlights
     * @return array{strengths: list<array{key: string, text: string, favorable: int|null}>, opportunities: list<array{key: string, text: string, favorable: int|null}>}
     */
    private function highlights(array $highlights): array
    {
        return ['strengths' => array_map($this->item(...), $highlights['strengths']), 'opportunities' => array_map($this->item(...), $highlights['opportunities'])];
    }

    /**
     * @return array{key: string, text: string, favorable: int|null}
     */
    private function item(ItemResult $result): array
    {
        return ['key' => $result->key, 'text' => ItemBank::find($result->key)->text, 'favorable' => $result->favorable];
    }

    /**
     * @param  list<array{driver: string, level: string}>  $priorities
     * @return list<array{driver: string, label: string, level: string, levelLabel: string}>
     */
    private function priorities(array $priorities): array
    {
        return array_map(fn (array $priority): array => [
            'driver' => $priority['driver'],
            'label' => Driver::from($priority['driver'])->label(),
            'level' => $priority['level'],
            'levelLabel' => Priority::from($priority['level'])->label(),
        ], $priorities);
    }

    /**
     * @param  array<string, GroupSummary>  $groups
     * @return list<array<string, mixed>>
     */
    private function groups(?CutAttribute $cut, array $groups, GroupSummary $company): array
    {
        $names = $cut === null ? [] : $this->names($cut, array_keys($groups));

        return array_map(fn (string $key, GroupSummary $group): array => [
            'key' => $key,
            'name' => $names[$key] ?? 'Sin nombre',
            'responses' => $group->responses,
            'index' => $group->index,
            'vsCompany' => $group->index === null || $company->index === null ? null : $group->index - $company->index,
            'recommendation' => $this->recommendation($group->recommendation),
            'drivers' => array_map(fn (DriverResult $driver, DriverResult $overall): array => [
                'key' => $driver->driver->value,
                'label' => $driver->driver->label(),
                'descriptive' => $driver->descriptive,
                'favorable' => $driver->favorable,
                'vsCompany' => $driver->favorable === null || $overall->favorable === null ? null : $driver->favorable - $overall->favorable,
            ], $group->drivers, $company->drivers),
            ...$this->highlights($group->highlights),
        ], array_keys($groups), $groups);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function names(CutAttribute $cut, array $keys): array
    {
        return match ($cut) {
            CutAttribute::WorkCenter => array_column(array_map(fn (WorkCenterSummary $center): array => [$center->id, $center->name], $this->workCenters->all()), 1, 0),
            CutAttribute::Area => $this->areas->names($keys),
            CutAttribute::Tenure => array_combine($keys, array_map(fn (string $key): string => TenureBand::from($key)->label(), $keys)),
            CutAttribute::Manager => array_map(fn ($person): string => "Equipo de {$person->name}", $this->roster->findMany($keys)),
        };
    }

    private function indexScore(GroupSummary $summary, SurveyResults $results): ?ItemResult
    {
        return $this->scoreOf($summary->index, array_map(fn (string $key): ?ItemResult => $summary->byKey[$key] ?? null, ItemBank::INDEX), $results->survey()->name);
    }

    private function score(DriverResult|ItemResult|null $of): ?ItemResult
    {
        return $of instanceof DriverResult ? $this->scoreOf($of->favorable, $of->items, $of->driver->value) : $of;
    }

    /**
     * A score made of several questions as one result, to compare it with the other wave: it has as many answers as the
     * question with the fewest.
     *
     * @param  list<ItemResult|null>  $items
     */
    private function scoreOf(?int $favorable, array $items, string $key): ?ItemResult
    {
        $answered = array_map(fn (?ItemResult $item): int => $item->answered ?? 0, $items);

        return $favorable === null || $answered === [] ? null : new ItemResult($key, true, min($answered), $favorable, null, null, null);
    }

    /**
     * @return array{points: int, direction: string|null}|null
     */
    private function delta(?ItemResult $now, ?ItemResult $before, bool $sameWording): ?array
    {
        $delta = WaveComparison::delta($now, $before, $sameWording, $this->thresholds);

        return $delta === null ? null : ['points' => $delta->points, 'direction' => $delta->direction?->value];
    }
}
