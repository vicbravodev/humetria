<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Enums\StatementState;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Billing\Support\StatementBalances;
use App\Modules\Tenancy\Contracts\CompanyRegistry;

final readonly class DescribeStatement
{
    public function __construct(private CompanyRegistry $companies, private StatementBalances $balances) {}

    /**
     * A statement as its page and its print view show it: the document with its snapshot, its lines and what explains them,
     * the payments applied to it and the adjustments that correct it. Every amount is already written as pesos.
     *
     * @return array{statement: array<string, mixed>, lines: list<array<string, string>>, payments: list<array<string, mixed>>, adjustments: list<array<string, mixed>>}
     */
    public function handle(Statement $statement): array
    {
        $account = BillingAccount::query()->findOrFail($statement->billing_account_id);
        $lines = $statement->lines()->get();
        $brief = $this->companies->find($statement->tenant_id);
        $draft = $statement->status === StatementStatus::Draft;
        $paid = $this->balances->paid([$statement->id])[$statement->id] ?? 0;
        $state = StatementState::of($statement->status, $statement->total_cents, $paid);
        $names = $this->names(array_filter([$statement->created_by, $statement->issued_by]));

        return [
            'statement' => [
                'id' => $statement->id,
                'account_id' => $statement->billing_account_id,
                'account_name' => $account->legal_name,
                'account_rfc' => $account->rfc,
                'company_id' => $statement->tenant_id,
                'trade_name' => $brief->tradeName ?? '',
                'legal_name' => $brief->legalName ?? '',
                'company_rfc' => $brief->rfc ?? '',
                'period' => ShortDate::month($statement->period_start),
                'period_range' => ShortDate::format($statement->period_start).' al '.ShortDate::format($statement->period_end->subDay()),
                'status' => $statement->status->value,
                'state' => $state->value,
                'state_label' => $state->label(),
                'is_adjustment' => $statement->adjusts_statement_id !== null,
                'adjusts_id' => $statement->adjusts_statement_id,
                'reason' => $statement->reason,
                'currency' => $statement->currency,
                'catalog_version' => $statement->catalog_version,
                'vat_percent' => $statement->vat_percent,
                'subtotal' => Money::format($statement->subtotal_cents),
                'vat' => Money::format($statement->vat_cents),
                'total' => Money::format($statement->total_cents),
                'paid' => $draft ? null : Money::format($paid),
                'balance' => $draft ? null : Money::format($statement->total_cents - $paid),
                'created_by' => $names[$statement->created_by] ?? '',
                'issued_by' => $statement->issued_by === null ? null : $names[$statement->issued_by] ?? '',
                'issued_at' => $statement->issued_at === null ? null : ShortDate::format($statement->issued_at->setTimezone(config()->string('app.display_timezone'))),
            ],
            'lines' => array_values($lines->map(fn ($line): array => [
                'label' => $line->label,
                'amount' => Money::format($line->amount_cents),
                'explanation' => $line->explanation,
                'code' => $line->code,
            ])->all()),
            'payments' => $this->payments($statement),
            'adjustments' => $this->adjustments($statement),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function payments(Statement $statement): array
    {
        $allocations = PaymentAllocation::query()->where('statement_id', $statement->id)->orderBy('created_at')->orderBy('id')->get();
        $payments = Payment::query()->whereIn('id', $allocations->pluck('payment_id')->all())->get()->keyBy('id');
        $rows = [];

        foreach ($allocations as $allocation) {
            $payment = $payments->get($allocation->payment_id);

            if ($payment !== null) {
                $rows[] = [
                    'payment_id' => $payment->id,
                    'reference' => $payment->reference,
                    'paid_on' => ShortDate::format($payment->paid_on),
                    'amount' => Money::format($allocation->amount_cents),
                    'reversed' => $payment->isReversed(),
                ];
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function adjustments(Statement $statement): array
    {
        return array_values(Statement::query()->where('adjusts_statement_id', $statement->id)->orderBy('created_at')->orderBy('id')->get()->map(fn (Statement $adjustment): array => [
            'id' => $adjustment->id,
            'total' => Money::format($adjustment->total_cents),
            'reason' => $adjustment->reason ?? '',
        ])->all());
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string> user names by id.
     */
    private function names(array $ids): array
    {
        $names = [];

        foreach (User::query()->whereIn('id', $ids)->get(['id', 'name']) as $user) {
            $names[$user->id] = $user->name;
        }

        return $names;
    }
}
