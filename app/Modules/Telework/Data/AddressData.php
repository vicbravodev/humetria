<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/**
 * A street address as a person types it, with the same fields and rules as a work center's. The company never comes from here.
 */
final readonly class AddressData
{
    public function __construct(
        public string $street,
        public string $exteriorNumber,
        public ?string $interiorNumber,
        public string $neighborhood,
        public string $postalCode,
        public string $municipality,
        public string $state,
    ) {}

    /** From validated input: what is missing or not text becomes empty (the rules already refused it). */
    public static function fromInput(mixed $input): self
    {
        $field = fn (string $key): string => is_array($input) && is_string($input[$key] ?? null) ? $input[$key] : '';
        $interior = $field('interior_number');

        return new self($field('street'), $field('exterior_number'), $interior === '' ? null : $interior, $field('neighborhood'), $field('postal_code'), $field('municipality'), $field('state'));
    }

    /**
     * @return array{street: string, exterior_number: string, interior_number: string|null, neighborhood: string, postal_code: string, municipality: string, state: string}
     */
    public function attributes(): array
    {
        return [
            'street' => $this->street,
            'exterior_number' => $this->exteriorNumber,
            'interior_number' => $this->interiorNumber,
            'neighborhood' => $this->neighborhood,
            'postal_code' => $this->postalCode,
            'municipality' => $this->municipality,
            'state' => $this->state,
        ];
    }
}
