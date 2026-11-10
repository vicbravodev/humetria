<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A company an account pays, as the account's page lists it: who it is, where it stands and what it contracted (nothing,
 * until a plan is assigned).
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AccountCompanyRow implements Arrayable
{
    /**
     * @param  array<string, mixed>|null  $subscription  null while the company has no plan.
     */
    public function __construct(
        public string $companyId,
        public string $tradeName,
        public string $legalName,
        public string $rfc,
        public string $slug,
        public string $status,
        public string $statusLabel,
        public int $workCenters,
        public int $people,
        public string $linkedSince,
        public ?array $subscription,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'trade_name' => $this->tradeName,
            'legal_name' => $this->legalName,
            'rfc' => $this->rfc,
            'slug' => $this->slug,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'work_centers' => $this->workCenters,
            'people' => $this->people,
            'linked_since' => $this->linkedSince,
            'subscription' => $this->subscription,
        ];
    }
}
