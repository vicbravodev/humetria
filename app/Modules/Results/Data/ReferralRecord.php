<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

use App\Modules\Results\Enums\Destination;
use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Enums\ReferralStatus;

/**
 * What HR records of a person's channeling (board V4-N035-Canalizacion): never an answer or a diagnosis. `referredOn` is the
 * Y-m-d day as written in Mexico; `remind` asks to be reminded 30 days after it.
 */
final readonly class ReferralRecord
{
    public function __construct(
        public ReferralStatus $status,
        public Destination $destination,
        public HowTold $told,
        public string $referredOn,
        public ?string $note,
        public bool $remind,
    ) {}
}
