<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Models\ComplaintChannel;

/** A channel reached by its code: the company's name for the page and the channel itself. */
final readonly class OpenedChannel
{
    public function __construct(public ComplaintChannel $channel, public string $company) {}
}
