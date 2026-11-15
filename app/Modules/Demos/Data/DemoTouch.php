<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

/**
 * One visit's signal about where the person came from: the campaign's own utm_* and, only for another site, its host
 * (never a URL). Empty means the visit had no signal, which is not the same as direct traffic.
 */
final readonly class DemoTouch
{
    public function __construct(
        public ?string $source = null,
        public ?string $medium = null,
        public ?string $campaign = null,
        public ?string $referrerHost = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->source === null && $this->medium === null && $this->campaign === null && $this->referrerHost === null;
    }
}
