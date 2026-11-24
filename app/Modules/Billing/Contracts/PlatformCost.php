<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Data\MessagingMargin;
use Carbon\CarbonImmutable;

/** What the platform pays to send, for the staff's overview: figures across companies, never one company's (the other side of PlatformRevenue). */
interface PlatformCost
{
    /** The WhatsApp of the calendar month (Mexico City) that has `$month`: messages, Meta's cost and the price charged. */
    public function whatsAppMonth(CarbonImmutable $month): MessagingMargin;
}
