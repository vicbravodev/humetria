<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * An area whose answers may be shown, and how many people of it answered.
 */
final readonly class AreaCount
{
    public function __construct(public string $areaId, public int $answered) {}
}
