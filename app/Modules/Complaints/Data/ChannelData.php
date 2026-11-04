<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Data;

use Carbon\CarbonImmutable;

/**
 * What the company may tell its people about its channel (NOM-035 5.7 d). Publishable as it is: no cases, no counts, no
 * handlers, no names.
 */
final readonly class ChannelData
{
    /**
     * @param  list<string>  $types  What the channel accepts, by type code.
     * @param  string|null  $url  The public address of the channel; null while it takes nothing.
     */
    public function __construct(
        public bool $available,
        public ?string $url,
        public ?CarbonImmutable $publishedOn,
        public array $types,
        public bool $anonymousAllowed,
        public int $acknowledgeWithinDays,
        public int $resolveWithinDays,
        public string $procedure,
    ) {}
}
