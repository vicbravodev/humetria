<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Events;

use Carbon\CarbonImmutable;

/** The company opened its channel to its people: the day it did, for the evidence of the dissemination (5.7 d). */
final readonly class ComplaintChannelPublished
{
    public function __construct(public string $tenantId, public CarbonImmutable $publishedOn) {}
}
