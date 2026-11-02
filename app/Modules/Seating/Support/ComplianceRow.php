<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\RowState;
use Carbon\CarbonImmutable;

/**
 * One requirement of a work center on the board: `r1` to `r13`, where it stands, how many things behind it need attention
 * (none when it is met) and, when there is one, since when the oldest of them has been waiting.
 */
final readonly class ComplianceRow
{
    public function __construct(public string $key, public RowState $state, public int $count = 0, public ?CarbonImmutable $since = null) {}
}
