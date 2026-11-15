<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Billing\Exceptions\InvalidPayment;
use App\Modules\Billing\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ReversePayment
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Reverses a payment recorded by mistake, with the reason: the payment stays on record and what it paid is owed again. It
     * is never deleted. It changes nothing of any company's access.
     *
     * @throws InvalidPayment
     */
    public function handle(Payment $payment, string $reason, User $by): Payment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidPayment('reason', 'Di por qué se revierte el pago.');
        }

        return DB::transaction(function () use ($payment, $reason, $by): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->isReversed()) {
                throw new InvalidPayment('payment', 'Ese pago ya se revirtió.');
            }

            $locked->update(['reversed_at' => now(), 'reversed_by' => $by->id, 'reversal_reason' => $reason]);

            Log::info('billing.payment_reversed', ['billing_account_id' => $locked->billing_account_id, 'amount_cents' => $locked->amount_cents]);
            $this->recordAudit->handle('billing.payment_reversed', $locked, [
                'billing_account_id' => $locked->billing_account_id,
                'amount_cents' => $locked->amount_cents,
                'reason' => $reason,
            ]);

            return $locked;
        });
    }
}
