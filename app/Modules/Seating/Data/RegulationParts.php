<?php

declare(strict_types=1);

namespace App\Modules\Seating\Data;

/**
 * The three parts the internal regulation must carry about the standing-work obligation (LFT 423): the risk factors, the
 * pauses and the place and availability of the seats, plus the rule of use of the seat (423 V).
 */
final readonly class RegulationParts
{
    public function __construct(
        public string $riskFactors,
        public string $pauses,
        public string $seatLocation,
        public string $usageRule,
    ) {}

    /**
     * @return array{risk_factors: string, pauses: string, seat_location: string, usage_rule: string}
     */
    public function toArray(): array
    {
        return ['risk_factors' => $this->riskFactors, 'pauses' => $this->pauses, 'seat_location' => $this->seatLocation, 'usage_rule' => $this->usageRule];
    }
}
