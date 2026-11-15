<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Exceptions\InvalidPayment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use Illuminate\Support\Str;

/**
 * Applies part of a payment to issued statements of its own account, without passing what is left of the payment or the
 * balance of any statement. The database refuses the same, but a person gets the message. Call it inside a transaction.
 */
final readonly class PaymentAllocator
{
    public function __construct(private StatementBalances $balances) {}

    /**
     * @param  array<string, int>  $allocations  cents, by statement id.
     *
     * @throws InvalidPayment
     */
    public function allocate(Payment $payment, array $allocations): void
    {
        if ($allocations === [] || min(array_values($allocations)) < 1) {
            throw new InvalidPayment('allocations', 'Elige a qué estados de cuenta se aplica y con cuánto.');
        }

        $applied = (int) PaymentAllocation::query()->where('payment_id', $payment->id)->sum('amount_cents');

        if (array_sum($allocations) > $payment->amount_cents - $applied) {
            throw new InvalidPayment('allocations', 'Lo aplicado es mayor que lo que queda del pago.');
        }

        $statements = $this->lockStatements($payment, array_keys($allocations));
        $paid = $this->balances->paid(array_keys($statements));
        $already = PaymentAllocation::query()->where('payment_id', $payment->id)->whereIn('statement_id', array_keys($allocations))->exists();

        if ($already) {
            throw new InvalidPayment('allocations', 'Ese pago ya se aplicó a uno de esos estados de cuenta.');
        }

        foreach ($allocations as $statementId => $cents) {
            $statement = $statements[$statementId] ?? throw new InvalidPayment('allocations', 'Elige estados de cuenta emitidos de esta cuenta.');

            if ($cents > $statement->total_cents - ($paid[$statementId] ?? 0)) {
                throw new InvalidPayment('allocations', 'No se puede aplicar más que el saldo de un estado de cuenta.');
            }
        }

        foreach ($allocations as $statementId => $cents) {
            $payment->allocations()->create(['statement_id' => $statementId, 'amount_cents' => $cents]);
        }
    }

    /**
     * The issued statements of the payment's account among those ids, locked until the transaction ends.
     *
     * @param  list<string>|list<int|string>  $ids
     * @return array<string, Statement>
     */
    private function lockStatements(Payment $payment, array $ids): array
    {
        $uuids = array_values(array_filter(array_map(strval(...), $ids), Str::isUuid(...)));
        $found = [];

        foreach (Statement::query()->where('billing_account_id', $payment->billing_account_id)->where('status', StatementStatus::Issued)->whereIn('id', $uuids)->lockForUpdate()->get() as $statement) {
            $found[$statement->id] = $statement;
        }

        return $found;
    }
}
