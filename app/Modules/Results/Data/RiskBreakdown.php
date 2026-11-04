<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

use App\Modules\Instruments\Data\RiskLevel;

/**
 * The risk results of an evaluation. `state` says whether there are figures: available; open (it still runs, so no
 * result exists yet); not_scored (it applies only the Guía I); below_minimum (fewer questionnaires than the minimum, so
 * not even how many). Without figures every list is empty and `answered` is 0. `areaLabel` names the area the figures
 * narrow to, or null when they are the center's: an area that stays hidden is not applied.
 */
final readonly class RiskBreakdown
{
    public const string AVAILABLE = 'available';

    public const string OPEN = 'open';

    public const string NOT_SCORED = 'not_scored';

    public const string BELOW_MINIMUM = 'below_minimum';

    /**
     * @param  list<int>  $final
     * @param  list<RiskGroup>  $categories
     * @param  list<RiskGroup>  $domains
     * @param  list<AreaResult>  $areas
     */
    public function __construct(
        public string $state,
        public int $answered = 0,
        public array $final = [],
        public array $categories = [],
        public array $domains = [],
        public array $areas = [],
        public int $hiddenAreas = 0,
        public ?string $areaLabel = null,
    ) {}

    /**
     * The one rule of what needs a Programa de intervención (num. 8.3, II.4, III.4; criterio C-30): the final score, each
     * category and each domain with at least one questionnaire in Medio, Alto or Muy alto, in that order. Without figures
     * (open, not scored, below the minimum) nothing is derived.
     *
     * @return list<RiskFocus>
     */
    public function needingProgram(): array
    {
        $foci = [self::focus('final', 'final', 'Calificación final', null, $this->final)];

        foreach ($this->categories as $group) {
            $foci[] = self::focus('category', $group->key, $group->label, $group->category, $group->levels);
        }

        foreach ($this->domains as $group) {
            $foci[] = self::focus('domain', $group->key, $group->label, $group->category, $group->levels);
        }

        return array_values(array_filter($foci));
    }

    /** The highest level among the places that need a Programa; null when none does. */
    public function highestNeedingProgram(): ?RiskLevel
    {
        $highest = null;

        foreach ($this->needingProgram() as $focus) {
            if ($highest === null || self::rank($focus->level) > self::rank($highest)) {
                $highest = $focus->level;
            }
        }

        return $highest;
    }

    /**
     * @param  list<int>  $levels  questionnaires in each level, Nulo to Muy alto
     */
    private static function focus(string $scope, string $key, string $label, ?string $category, array $levels): ?RiskFocus
    {
        $order = RiskLevel::cases();
        $highest = null;
        $questionnaires = 0;

        foreach ($levels as $index => $count) {
            if ($index >= self::rank(RiskLevel::Medium) && $count > 0) {
                $highest = $order[$index];
                $questionnaires += $count;
            }
        }

        return $highest === null ? null : new RiskFocus($scope, $key, $label, $category, $highest, $questionnaires);
    }

    /** The place of a level from Nulo (0) to Muy alto (4). */
    private static function rank(RiskLevel $level): int
    {
        return array_flip(array_map(fn (RiskLevel $case): string => $case->value, RiskLevel::cases()))[$level->value];
    }

    public static function open(): self
    {
        return new self(self::OPEN);
    }

    public static function notScored(): self
    {
        return new self(self::NOT_SCORED);
    }

    public static function belowMinimum(): self
    {
        return new self(self::BELOW_MINIMUM);
    }
}
