<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Enums\PayBasis;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\RaiseUnit;
use App\Modules\Compensation\Enums\ScenarioSource;
use Carbon\CarbonImmutable;

/**
 * What the editor of a scenario sends. Raises are in basis points (500 is 5 %), or in cents a month when `raiseUnit` is an
 * amount; a trusted raise of null copies the unionized one. A scenario of the `average` source carries the headcount, the average
 * monthly salary (cents), the years of service and the group of its payroll, and is always paid monthly; one of the `roster` source
 * carries none of them and says how its salary is paid. `assumptions` holds only the ones the person typed, by key, in basis points: the rest are the platform's.
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
        public RaiseUnit $raiseUnit = RaiseUnit::Percent,
        public ScenarioSource $source = ScenarioSource::Roster,
        public ?int $averageWorkers = null,
        public ?int $averageMonthlySalaryCents = null,
        public ?int $averageTenureYears = null,
        public ?PayGroup $averageGroup = null,
        public PayBasis $payBasis = PayBasis::Daily,
    ) {}
}
