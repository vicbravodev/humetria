<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

use App\Modules\Telework\Enums\MaritalStatus;

/**
 * What RH gives (and the employee's job title supplies) to move an aspirant into the roster: the data of numeral 5.1.
 * Anything missing is refused by `JoinRoster`, so it can be built from partial input.
 */
final readonly class RosterData
{
    public function __construct(
        public ?MaritalStatus $maritalStatus,
        public ?string $activities,
        public ?string $jobProfile,
        public ?string $contactPhone,
        public ?AddressData $home,
        public ?string $noEquipmentReason,
    ) {}
}
