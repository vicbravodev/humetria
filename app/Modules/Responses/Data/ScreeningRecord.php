<?php

declare(strict_types=1);

namespace App\Modules\Responses\Data;

use Carbon\CarbonImmutable;

/**
 * A person whose Guía I requires a clinical assessment, and when they answered it.
 */
final readonly class ScreeningRecord
{
    public function __construct(public string $employeeId, public CarbonImmutable $answeredAt) {}
}
