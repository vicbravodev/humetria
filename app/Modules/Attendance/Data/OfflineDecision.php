<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Enums\TimeBasis;
use Carbon\CarbonImmutable;

/**
 * What the rule of time decided for a mark made without a connection (criterio 3.3.1): the time that counts, what bounded
 * it and the flags Recursos Humanos sees.
 */
final readonly class OfflineDecision
{
    /**
     * @param  list<ReviewFlag>  $flags
     */
    public function __construct(public CarbonImmutable $countedAt, public TimeBasis $basis, public array $flags) {}
}
