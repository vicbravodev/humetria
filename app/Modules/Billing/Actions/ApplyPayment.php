<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Billing\Exceptions\InvalidPayment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Support\PaymentAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ApplyPayment
{
    public function __construct(private RecordAudit $recordAudit, private PaymentAllocator $allocator) {}

    /**
     * Applies what is left of a recorded payment to issued statements of its account, with the same limits as when it was
     * recorded: not more than is left of the payment, nor than the balance of a statement. It changes nothing of any company's
     * access.
     *
     * @param  array<string, int>  $allocations  cents, by statement id.
     *
     * @throws InvalidPayment
     */
    public function handle(Payment $payment, array $allocations, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $allocations): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->isReversed()) {
                throw new InvalidPayment('payment', 'Un pago revertido no se puede aplicar.');
            }

            $this->allocator->allocate($locked, $allocations);

            $applied = array_sum($allocations);
            Log::info('billing.payment_applied', ['billing_account_id' => $locked->billing_account_id, 'payment_id' => $locked->id, 'allocated_cents' => $applied]);
            $this->recordAudit->handle('billing.payment_applied', $locked, [
                'billing_account_id' => $locked->billing_account_id,
                'amount_cents' => $locked->amount_cents,
                'allocated_cents' => $applied,
                'statements' => count($allocations),
            ]);

            return $locked;
        });
    }
}
