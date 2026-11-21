<?php

declare(strict_types=1);

namespace App\Modules\Leave\Events;

/**
 * A person's absence came or went: a request was approved, or an approved or enjoyed one was cancelled. Its dates (Y-m-d,
 * both included) are the days that changed. Dispatched inside the change's transaction. Ids and dates only: never the type
 * of the leave (criterio C-22), nor a reason.
 */
final readonly class AbsenceChanged
{
    public function __construct(public string $requestId, public string $employeeId, public string $from, public string $to) {}
}
