<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\Profile;
use Carbon\CarbonImmutable;

/** What the console sends to move a company to another payer, or to none, from a cut. */
final readonly class TransferData
{
    /**
     * @param  string|null  $toAccountId  null leaves the company without a payer.
     * @param  Profile|null  $profile  how it is priced from then on; null keeps the one it has, which the new account must be able to hold.
     */
    public function __construct(
        public ?string $toAccountId,
        public CarbonImmutable $effectiveOn,
        public string $reason = '',
        public ?Profile $profile = null,
    ) {}
}
