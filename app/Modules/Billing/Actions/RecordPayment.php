<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Billing\Data\PaymentData;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Exceptions\InvalidPayment;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\StatementBalances;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class RecordPayment
{
    public function __construct(private RecordAudit $recordAudit, private StatementBalances $balances) {}

    /**
     * Records a transfer of the account and applies it to issued statements of that account, in whole or in part; what is not
     * applied stays unapplied. It changes nothing of any company's access, dates, restriction or modules: paying is not
     * activating.
     *
     * @throws InvalidPayment
     */
    public function handle(BillingAccount $account, PaymentData $data, User $recorder): Payment
    {
        $reference = trim($data->reference);
        $this->guard($data, $reference);

        try {
            return DB::transaction(fn (): Payment => $this->write($account, $data, $reference, $recorder));
        } catch (UniqueConstraintViolationException) {
            throw new InvalidPayment('reference', 'Ya hay un pago con esa referencia en esta cuenta.');
        }
    }

    /** @throws InvalidPayment */
    private function guard(PaymentData $data, string $reference): void
    {
        if ($reference === '') {
            throw new InvalidPayment('reference', 'Pon la referencia del pago.');
        }

        if ($data->amountCents < 1) {
            throw new InvalidPayment('amount', 'El pago debe ser de más de cero.');
        }

        if (min([1, ...array_values($data->allocations)]) < 1) {
            throw new InvalidPayment('allocations', 'Cada importe aplicado debe ser de más de cero.');
        }

        if (array_sum($data->allocations) > $data->amountCents) {
            throw new InvalidPayment('allocations', 'Lo aplicado es mayor que el pago.');
        }
    }

    private function write(BillingAccount $account, PaymentData $data, string $reference, User $recorder): Payment
    {
        $statements = $this->lockStatements($account, array_keys($data->allocations));
        $paid = $this->balances->paid(array_keys($statements));

        foreach ($data->allocations as $statementId => $cents) {
            $statement = $statements[$statementId] ?? throw new InvalidPayment('allocations', 'Elige estados de cuenta emitidos de esta cuenta.');

            if ($cents > $statement->total_cents - ($paid[$statementId] ?? 0)) {
                throw new InvalidPayment('allocations', 'No se puede aplicar más que el saldo de un estado de cuenta.');
            }
        }

        $payment = Payment::query()->create([
            'billing_account_id' => $account->id,
            'amount_cents' => $data->amountCents,
            'paid_on' => $data->paidOn,
            'reference' => $reference,
            'note' => $data->note === null || trim($data->note) === '' ? null : trim($data->note),
            'recorded_by' => $recorder->id,
        ]);

        foreach ($data->allocations as $statementId => $cents) {
            $payment->allocations()->create(['statement_id' => $statementId, 'amount_cents' => $cents]);
        }

        $applied = array_sum($data->allocations);
        Log::info('billing.payment_recorded', ['billing_account_id' => $account->id, 'amount_cents' => $data->amountCents, 'allocated_cents' => $applied]);
        $this->recordAudit->handle('billing.payment_recorded', $payment, [
            'billing_account_id' => $account->id,
            'amount_cents' => $data->amountCents,
            'allocated_cents' => $applied,
            'statements' => count($data->allocations),
        ]);

        return $payment;
    }

    /**
     * The issued statements of the account among those ids, locked until the payment is written.
     *
     * @param  list<string>|list<int|string>  $ids
     * @return array<string, Statement>
     */
    private function lockStatements(BillingAccount $account, array $ids): array
    {
        $uuids = array_values(array_filter(array_map(strval(...), $ids), Str::isUuid(...)));
        $found = [];

        foreach (Statement::query()->where('billing_account_id', $account->id)->where('status', StatementStatus::Issued)->whereIn('id', $uuids)->lockForUpdate()->get() as $statement) {
            $found[$statement->id] = $statement;
        }

        return $found;
    }
}
