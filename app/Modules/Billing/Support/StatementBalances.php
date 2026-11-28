<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;

/**
 * What has been paid of statements, derived from the payments applied to them: an issued statement never stores it. A payment
 * that was reversed counts as nothing.
 */
final class StatementBalances
{
    /**
     * @param  list<string>  $statementIds
     * @return array<string, int> cents paid, by statement id; a statement with nothing paid is left out.
     */
    public function paid(array $statementIds): array
    {
        $paid = [];

        foreach (PaymentAllocation::query()->whereIn('statement_id', $statementIds)->whereHas('payment', fn ($payment) => $payment->standing())->get(['statement_id', 'amount_cents']) as $allocation) {
            $paid[$allocation->statement_id] = ($paid[$allocation->statement_id] ?? 0) + $allocation->amount_cents;
        }

        return $paid;
    }

    /** What the account still owes: the sum of its statements' balances (Statement::withBalance), never less than zero. */
    public function outstanding(BillingAccount $account): int
    {
        return array_sum(Statement::query()->withBalance()->where('billing_account_id', $account->id)->get()->map(fn (Statement $statement): int => $statement->balance_cents)->all());
    }
}
