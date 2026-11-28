<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\DueDate;
use App\Modules\Billing\Support\Money;
use Carbon\CarbonImmutable;

final readonly class SummarizeAccountYear
{
    /**
     * The account's year so far, in the panel's time zone: what its issued statements of this year's months add up to (credits
     * and charges included) and how many there are (adjustments aside), what its standing payments brought and how many, and
     * on average how many days after they were due it paid the statements it settled this year (negative: before; null: none).
     *
     * @return array{year: int, billed: string, billed_statements: int, paid: string, payments: int, average_days: int|null}
     */
    public function handle(BillingAccount $account): array
    {
        $year = CarbonImmutable::now(config()->string('app.display_timezone'))->year;
        $statements = Statement::query()->selectBalance()
            ->where('billing_account_id', $account->id)
            ->where('status', StatementStatus::Issued)
            ->get()
            ->all();
        $billed = array_filter($statements, fn (Statement $statement): bool => $statement->period_start->year === $year);
        $paid = array_map(fn (Payment $payment): int => $payment->amount_cents, Payment::query()->standing()->where('billing_account_id', $account->id)->whereYear('paid_on', $year)->get(['amount_cents'])->all());

        return [
            'year' => $year,
            'billed' => Money::format(array_sum(array_map(fn (Statement $statement): int => $statement->total_cents, $billed))),
            'billed_statements' => count(array_filter($billed, fn (Statement $statement): bool => $statement->adjusts_statement_id === null)),
            'paid' => Money::format(array_sum($paid)),
            'payments' => count($paid),
            'average_days' => $this->averageDaysLate($statements, $year),
        ];
    }

    /**
     * @param  array<int, Statement>  $statements  issued, each with its balance.
     */
    private function averageDaysLate(array $statements, int $year): ?int
    {
        $settled = array_filter($statements, fn (Statement $statement): bool => $statement->balance_cents === 0 && $statement->total_cents > 0);
        $lastPaid = $this->lastPaid(array_values(array_map(fn (Statement $statement): string => $statement->id, $settled)));
        $days = [];

        foreach ($settled as $statement) {
            $paid = $lastPaid[$statement->id] ?? null;

            if ($paid !== null && $statement->issued_at !== null && $paid->year === $year) {
                $days[] = DueDate::of($statement->issued_at)->diffInDays($paid, false);
            }
        }

        return $days === [] ? null : (int) round(array_sum($days) / count($days));
    }

    /**
     * The day of the last standing payment applied to each of the statements.
     *
     * @param  list<string>  $statementIds
     * @return array<string, CarbonImmutable>
     */
    private function lastPaid(array $statementIds): array
    {
        $last = [];
        $rows = PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->whereNull('payments.reversed_at')
            ->whereIn('payment_allocations.statement_id', $statementIds)
            ->groupBy('payment_allocations.statement_id')
            ->toBase()
            ->selectRaw('payment_allocations.statement_id, max(payments.paid_on) as paid_on')
            ->get();

        foreach ($rows as $row) {
            if (is_string($row->statement_id) && is_string($row->paid_on)) {
                $last[$row->statement_id] = CarbonImmutable::parse($row->paid_on, config()->string('app.display_timezone'));
            }
        }

        return $last;
    }
}
