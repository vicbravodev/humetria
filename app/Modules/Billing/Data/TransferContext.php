<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;

/** What a move of a company comes down to once the rules agree: who pays now, who will, and how the plan is priced afterwards. */
final readonly class TransferContext
{
    public function __construct(
        public AccountCompany $link,
        public BillingAccount $from,
        public ?BillingAccount $to,
        public ?Subscription $subscription,
        public ?Profile $profileAfter,
    ) {}
}
