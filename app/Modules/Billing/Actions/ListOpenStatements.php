<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Billing\Support\StatementBalances;
use App\Modules\Tenancy\Contracts\CompanyRegistry;

final readonly class ListOpenStatements
{
    private const int LIMIT = 50;

    public function __construct(private CompanyRegistry $companies, private StatementBalances $balances) {}

    /**
     * The issued statements of the account that still have a balance, the oldest month first, fifty at most: the options a
     * payment can be applied to.
     *
     * @return list<array{id: string, label: string, balance: string, balance_input: string}>
     */
    public function handle(BillingAccount $account): array
    {
        $statements = Statement::query()
            ->where('billing_account_id', $account->id)
            ->withBalance()
            ->orderBy('period_start')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        $briefs = $this->companies->many(array_values(array_unique(array_map(fn (Statement $statement): string => $statement->tenant_id, $statements->all()))));
        $paid = $this->balances->paid(array_values(array_map(fn (Statement $statement): string => $statement->id, $statements->all())));

        return array_values($statements->map(function (Statement $statement) use ($briefs, $paid): array {
            $balance = $statement->total_cents - ($paid[$statement->id] ?? 0);

            return [
                'id' => $statement->id,
                'label' => $briefs[$statement->tenant_id]->tradeName.' · '.ShortDate::month($statement->period_start),
                'balance' => Money::format($balance),
                'balance_input' => Money::input($balance),
            ];
        })->all());
    }
}
