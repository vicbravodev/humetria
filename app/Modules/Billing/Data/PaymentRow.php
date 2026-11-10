<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A payment as the account's page lists it: what came in, how much of it settled statements, what is still unapplied (and so whether more can be applied), and, if it was reversed, why.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PaymentRow implements Arrayable
{
    public function __construct(
        public string $id,
        public string $reference,
        public string $paidOn,
        public string $amount,
        public string $applied,
        public string $unapplied,
        public ?string $note,
        public string $recordedBy,
        public bool $reversed,
        public ?string $reversalReason,
        public bool $canApply = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'paid_on' => $this->paidOn,
            'amount' => $this->amount,
            'applied' => $this->applied,
            'unapplied' => $this->unapplied,
            'note' => $this->note,
            'recorded_by' => $this->recordedBy,
            'reversed' => $this->reversed,
            'reversal_reason' => $this->reversalReason,
            'can_apply' => $this->canApply,
        ];
    }
}
