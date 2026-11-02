<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

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
