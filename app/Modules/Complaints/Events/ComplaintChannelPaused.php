<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Events;

/** The channel stopped taking complaints for now. */
final readonly class ComplaintChannelPaused
{
    public function __construct(public string $tenantId) {}
}
