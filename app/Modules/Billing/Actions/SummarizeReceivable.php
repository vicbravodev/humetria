<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\StatementBalances;

final readonly class SummarizeReceivable
{
    public function __construct(private StatementBalances $balances) {}

    /**
     * What the account owes across its issued statements, and how many still have a balance.
     *
     * @return array{outstanding: string, open_statements: int}
     */
    public function handle(BillingAccount $account): array
    {
        return [
            'outstanding' => Money::format($this->balances->outstanding($account)),
            'open_statements' => Statement::query()->where('billing_account_id', $account->id)->withBalance()->count(),
        ];
    }
}
