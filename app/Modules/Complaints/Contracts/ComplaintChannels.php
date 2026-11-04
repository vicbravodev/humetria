<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Contracts;

use App\Modules\Complaints\Data\ChannelData;

/**
 * The current company's channel for complaints and reports, as other modules need it to tell the people about it.
 */
interface ComplaintChannels
{
    public function current(): ChannelData;
}
