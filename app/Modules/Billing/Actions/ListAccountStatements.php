<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\StatementRow;
use App\Modules\Billing\Enums\StatementState;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Billing\Support\StatementBalances;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAccountStatements
{
    private const int PER_PAGE = 25;

    public function __construct(private CompanyRegistry $companies, private StatementBalances $balances) {}

    /**
     * The statements of the account, newest month first, a page at a time, with what was paid of each and what is left.
     *
     * @return LengthAwarePaginator<int, StatementRow>
     */
    public function handle(BillingAccount $account): LengthAwarePaginator
    {
        $page = Statement::query()
            ->where('billing_account_id', $account->id)
            ->orderByDesc('period_start')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'statements_page')
            ->withQueryString();

        $items = $page->items();
        $briefs = $this->companies->many(array_values(array_unique(array_map(fn (Statement $statement): string => $statement->tenant_id, $items))));
        $paid = $this->balances->paid(array_values(array_map(fn (Statement $statement): string => $statement->id, $items)));

        return $page->through(function (Statement $statement) use ($briefs, $paid): StatementRow {
            $settled = $paid[$statement->id] ?? 0;
            $state = StatementState::of($statement->status, $statement->total_cents, $settled);
            $draft = $statement->status === StatementStatus::Draft;

            return new StatementRow(
                $statement->id,
                $statement->tenant_id,
                $briefs[$statement->tenant_id]->tradeName,
                ShortDate::month($statement->period_start),
                $statement->adjusts_statement_id !== null,
                Money::format($statement->total_cents),
                $draft ? null : Money::format($settled),
                $draft ? null : Money::format($statement->total_cents - $settled),
                $state->value,
                $state->label(),
            );
        });
    }
}
