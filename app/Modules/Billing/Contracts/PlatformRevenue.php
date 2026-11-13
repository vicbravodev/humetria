<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

/** What the platform earns, for the staff's overview: figures across companies, never one company's. */
interface PlatformRevenue
{
    /** The monthly price, before VAT, of every subscription that pays now (active or cancelling at the end of its term), in cents. */
    public function monthlyContractedCents(): int;
}
