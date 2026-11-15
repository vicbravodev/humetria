<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * What an account pays across all its companies, for the head of its page: how many have a plan, what they add up to without
 * and with VAT, and when the next one renews. The companies that need a quotation are counted and left out of the sums.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AccountSummary implements Arrayable
{
    /**
     * @param  array{applies: bool, reason: string}|null  $discount  why the Integral discount applies or not; null when the account has no channel Integral plan.
     */
    public function __construct(
        public int $companies,
        public int $withPlan,
        public ?string $subtotal,
        public ?string $vat,
        public ?string $total,
        public int $requiringQuotation,
        public ?string $nextRenewal,
        public ?array $discount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'companies' => $this->companies,
            'with_plan' => $this->withPlan,
            'without_plan' => $this->companies - $this->withPlan,
            'subtotal' => $this->subtotal,
            'vat' => $this->vat,
            'total' => $this->total,
            'requiring_quotation' => $this->requiringQuotation,
            'next_renewal' => $this->nextRenewal,
            'discount' => $this->discount,
        ];
    }
}
