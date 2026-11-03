<?php

declare(strict_types=1);

namespace App\Modules\Seating\Events;

/**
 * A person answered about their footwear and discomfort. It carries ids only: never what was answered.
 */
final readonly class PersonInputSubmitted
{
    public function __construct(public string $employeeId, public string $workCenterId) {}
}
