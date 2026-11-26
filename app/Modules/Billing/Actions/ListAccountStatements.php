<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\StatementRow;
use App\Modules\Billing\Enums\StatementState;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\DueDate;
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
     * The statements of the account (or only the issued ones still owing), newest month first, a page at a time, with what was
     * paid of each, what is left and the days each was issued and is due.
     *
     * @return LengthAwarePaginator<int, StatementRow>
     */
    public function handle(BillingAccount $account, bool $open = false): LengthAwarePaginator
    {
        $page = Statement::query()
            ->where('billing_account_id', $account->id)
            ->when($open, fn ($query) => $query->withBalance(), fn ($query) => $query->selectBalance())
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
            $state = StatementState::of($statement->status, $statement->total_cents, $settled, $statement->balance_cents);
            $draft = $statement->status === StatementStatus::Draft;

            return new StatementRow(
                $statement->id,
                $statement->tenant_id,
                $briefs[$statement->tenant_id]->tradeName,
                ShortDate::month($statement->period_start),
                $statement->adjusts_statement_id !== null,
                Money::format($statement->total_cents),
                $draft ? null : Money::format($settled),
                $draft ? null : Money::format($statement->balance_cents),
                $state->value,
                $state->label(),
                $statement->issued_at === null ? null : ShortDate::format($statement->issued_at->setTimezone(config()->string('app.display_timezone'))),
                $statement->issued_at === null ? null : ShortDate::format(DueDate::of($statement->issued_at)),
            );
        });
    }
}
