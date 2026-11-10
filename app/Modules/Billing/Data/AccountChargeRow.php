<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * An extra charge as the account's page lists it, with its amount already written as pesos and its days for display.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AccountChargeRow implements Arrayable
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $tradeName,
        public string $kind,
        public string $kindLabel,
        public string $description,
        public string $scope,
        public string $amount,
        public string $chargeOn,
        public ?string $endsOn,
        public string $approvedBy,
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
            'kind' => $this->kind,
            'kind_label' => $this->kindLabel,
            'description' => $this->description,
            'scope' => $this->scope,
            'amount' => $this->amount,
            'charge_on' => $this->chargeOn,
            'ends_on' => $this->endsOn,
            'approved_by' => $this->approvedBy,
        ];
    }
}
