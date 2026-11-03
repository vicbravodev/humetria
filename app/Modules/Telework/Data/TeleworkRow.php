<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/**
 * One row of the inspection table 10.3 for a work center. `state` is done, partial, missing or not_applicable, and
 * `detail` says why in words and counts.
 */
final readonly class TeleworkRow
{
    public function __construct(
        public string $numeral,
        public string $label,
        public string $state,
        public string $stateLabel,
        public ?string $detail,
    ) {}
}
