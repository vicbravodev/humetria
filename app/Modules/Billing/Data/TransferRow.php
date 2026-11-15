<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A move of a company as an account's page lists it: seen from that account, it either left (out) or arrived (in), with the
 * account on the other side, the cut it took effect, the reason and who decided it.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TransferRow implements Arrayable
{
    /**
     * @param  'out'|'in'  $direction
     * @param  string|null  $profile  how its pricing changed, if it did.
     */
    public function __construct(
        public string $id,
        public string $company,
        public string $direction,
        public string $other,
        public string $effectiveOn,
        public string $reason,
        public string $by,
        public ?string $profile,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company' => $this->company,
            'direction' => $this->direction,
            'other' => $this->other,
            'effective_on' => $this->effectiveOn,
            'reason' => $this->reason,
            'by' => $this->by,
            'profile' => $this->profile,
        ];
    }
}
