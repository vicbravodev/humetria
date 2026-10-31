<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/** An item of equipment delivered to the person, waiting for them to confirm they received it or refuse it. */
final readonly class PendingEquipment
{
    public function __construct(public string $id, public string $title, public string $kind, public string $deliveredOn) {}
}
