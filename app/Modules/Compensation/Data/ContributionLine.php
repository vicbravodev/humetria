<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/** One employer contribution of a period, in cents. `calculated` is false when its input was missing (no premium, no state rate). */
final readonly class ContributionLine
{
    public function __construct(public string $key, public int $cents, public bool $calculated) {}
}
