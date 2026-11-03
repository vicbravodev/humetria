<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\RequirementState;

/**
 * A line of the board for a work center: its code (D1 to D10), the numeral of the norm it comes from, where it stands and,
 * when it helps, why in words and counts.
 */
final readonly class Requirement
{
    public function __construct(
        public string $code,
        public string $numeral,
        public string $label,
        public RequirementState $state,
        public ?string $detail,
    ) {}

    /**
     * @return array{code: string, numeral: string, label: string, state: array{value: string, label: string}, detail: string|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'numeral' => $this->numeral,
            'label' => $this->label,
            'state' => ['value' => $this->state->value, 'label' => $this->state->label()],
            'detail' => $this->detail,
        ];
    }
}
