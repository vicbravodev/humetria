<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

use App\Modules\Tenancy\Enums\MexicanState;

/**
 * What a person fills in for a work center. The company never comes from here: it is the current one.
 */
final readonly class WorkCenterData
{
    public function __construct(
        public string $name,
        public string $street,
        public string $exteriorNumber,
        public ?string $interiorNumber,
        public string $neighborhood,
        public string $postalCode,
        public string $municipality,
        public MexicanState $state,
        public string $mainActivity,
    ) {}

    /**
     * @return array{name: string, street: string, exterior_number: string, interior_number: string|null, neighborhood: string, postal_code: string, municipality: string, state: MexicanState, main_activity: string}
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'street' => $this->street,
            'exterior_number' => $this->exteriorNumber,
            'interior_number' => $this->interiorNumber,
            'neighborhood' => $this->neighborhood,
            'postal_code' => $this->postalCode,
            'municipality' => $this->municipality,
            'state' => $this->state,
            'main_activity' => $this->mainActivity,
        ];
    }
}
