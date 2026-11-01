<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\RowState;

/** One row of the inspection table 10.3 for a work center: the numeral, what it asks, where it stands and why. */
final readonly class ComplianceRow
{
    public function __construct(
        public string $numeral,
        public string $label,
        public RowState $state,
        public ?string $detail,
    ) {}
}
