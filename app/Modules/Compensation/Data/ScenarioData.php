<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use Carbon\CarbonImmutable;

/**
 * What the editor of a scenario sends. Raises are in basis points (500 is 5 %); a trusted raise of null copies the
 * unionized one. `assumptions` holds only the ones the person typed, by key, in basis points: the rest are the platform's.
 */
final readonly class ScenarioData
{
    /**
     * @param  ?string  $id  null to create one
     * @param  list<TermChange>  $termChanges
     * @param  array<string, int>  $assumptions
     */
    public function __construct(
        public ?string $id,
        public string $name,
        public CarbonImmutable $effectiveOn,
        public int $retroactiveMonths,
        public int $horizonYears,
        public ?int $unionizedRaiseBp,
        public ?int $trustedRaiseBp,
        public array $termChanges,
        public array $assumptions,
        public ?string $defaultState,
    ) {}
}
