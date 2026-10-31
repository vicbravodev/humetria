<?php

declare(strict_types=1);

namespace App\Modules\Telework\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A teleworker reported a severe traumatic event in a written notice (numeral 6.3, criterio C-27). Public API: the
 * NOM-035 side may offer Guía I to the person. It carries ids only, never the description. Dispatched once the notice
 * is committed.
 */
final readonly class TraumaticEventReported implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $tenantId,
        public string $employeeId,
        public string $workCenterId,
    ) {}
}
