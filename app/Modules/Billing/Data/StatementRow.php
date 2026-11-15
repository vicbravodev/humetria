<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A statement as the account's page lists it, amounts already written as pesos. A draft has nothing paid and no balance yet.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class StatementRow implements Arrayable
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $tradeName,
        public string $period,
        public bool $isAdjustment,
        public string $total,
        public ?string $paid,
        public ?string $balance,
        public string $state,
        public string $stateLabel,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'trade_name' => $this->tradeName,
            'period' => $this->period,
            'is_adjustment' => $this->isAdjustment,
            'total' => $this->total,
            'paid' => $this->paid,
            'balance' => $this->balance,
            'state' => $this->state,
            'state_label' => $this->stateLabel,
        ];
    }
}
