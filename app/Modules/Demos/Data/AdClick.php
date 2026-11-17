<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

use App\Modules\Demos\Enums\AdClickType;

/** The last ad click known of whoever asked for a demo: what Google Ads needs to learn which click became a request. */
final readonly class AdClick
{
    public function __construct(
        public AdClickType $type,
        public string $id,
    ) {}
}
