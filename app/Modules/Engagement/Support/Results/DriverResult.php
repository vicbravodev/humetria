<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\Driver;

/** A driver with its questions. `favorable` is `null` for a descriptive driver or when one of its questions is hidden. */
final readonly class DriverResult
{
    /** @param  list<ItemResult>  $items */
    public function __construct(
        public Driver $driver,
        public bool $descriptive,
        public ?int $favorable,
        public array $items,
    ) {}
}
