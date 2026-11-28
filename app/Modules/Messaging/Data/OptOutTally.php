<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/** How many people asked not to be written to: from every company, from one, and how many of them did in the last 30 days. */
final readonly class OptOutTally
{
    public function __construct(public int $everyCompany, public int $oneCompany, public int $lastThirtyDays) {}
}
